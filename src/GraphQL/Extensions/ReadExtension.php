<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataList;
use SilverStripe\GraphQL\Manager;
use SilverStripe\Versioned\Versioned;
use InvalidArgumentException;
use DateTime;

class ReadExtension extends Extension
{
    public function updateList(DataList &$list, $args)
    {
        if (!isset($args['Versioning']) || !isset($args['Versioning']['Mode'])) {
            return;
        }
        $mode = $args['Versioning']['Mode'];
        switch ($mode) {
            case Versioned::LIVE:
            case Versioned::DRAFT:
                $list = $list->setDataQueryParam('Versioned.mode', 'stage')
                             ->setDataQueryParam('Versioned.stage', $mode);
                break;
            case 'archive':
                if (!isset($args['ArchiveDate'])) {
                    throw new InvalidArgumentException(sprintf(
                        'You must provide a Date parameter when using the "%s" mode',
                        $mode
                    ));
                }
                $date = $args['ArchiveDate'];
                if (!$this->isValidDate($date)) {
                    throw new InvalidArgumentException(sprintf(
                        'Invalid date: "%s". Must be YYYY-MM-DD format'
                    ));
                }

                $list->setDataQueryParam('Versioned.mode', $mode)
                     ->setDataQueryParam('Versioned.date', $date);
                break;
            case 'latest_versions':
                $list = $list->setDataQueryParam('Versioned.mode', $mode);
                break;
            case 'status':
                if (!isset($args['Status'])) {
                    throw new InvalidArgumentException(sprintf(
                        'You must provide a Status parameter when using the "%s" mode',
                        $mode
                    ));
                }

                $baseTable = $owner->getDataObjectInstance()->baseTable();
                $liveTable = $baseTable . '_Live';
                $versionsTable = $baseTable . '_Versions';
                $draftTable = $baseTable . '_Draft';
                $statuses = $args['Status'];
                $usingArchive = in_array('archived', $statuses);
                if ($usingArchive) {
                    $queryMode = 'latest_versions';
                    $mainTable = $versionsTable;
                    $idField = 'RecordID';
                    $list = $list->leftJoin(
                        $baseTable,
                        "\"{$baseTable}\".\"ID\" = \"{$mainTable}\".\"{$idField}\"",
                        $draftTable
                    );
                    $list->dataQuery()->query()->renameTable($draftTable, $baseTable);
                } else {
                    $queryMode = Versioned::DRAFT;
                    $mainTable = $baseTable;
                    $idField = 'ID';
                }
                $list = $list->setDataQueryParam('Versioned.mode', $queryMode);
                $list = $list->leftJoin(
                    $liveTable,
                    "\"{$liveTable}\".\"ID\" = \"{$mainTable}\".\"{$idField}\""
                );
                $conditions = [
                    in_array('modified', $statuses)
                        ? "\"{$draftTable}\".\"Version\" <> \"{$liveTable}\".\"Version\" && \"{$liveTable}\".\"ID\" IS NOT NULL"
                        : null,
                    in_array('archived', $statuses)
                        ? "\"{$liveTable}\".\"ID\" IS NULL AND \"SiteTree_Draft\".\"ID\" IS NULL"
                        : null,
                    in_array('draft', $statuses)
                        ? "\"{$liveTable}\".\"ID\" IS NULL AND \"{$draftTable}\".\"ID\" IS NOT NULL"
                        : null
                ];
                $list = $list->whereAny(array_filter($conditions));

                break;
        }

        return $list;
    }

    /**
     * @param array $args
     * @param Manager $manager
     */
    public function updateArgs(&$args, Manager $manager)
    {
        $args['Versioning'] = [
            'type' => $manager->getType('VersionedReadInputType'),
        ];
    }

    /**
     * Returns true if date is in proper YYYY-MM-DD format
     * @param string $date
     * @return bool
     */
    protected function isValidDate($date)
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);

        return ($dt !== false && !array_sum($dt->getLastErrors()));
    }
}