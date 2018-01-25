<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Read;
use SilverStripe\ORM\DataList;
use SilverStripe\GraphQL\Manager;
use SilverStripe\ORM\DataQuery_SubGroup;
use SilverStripe\Versioned\Versioned;
use InvalidArgumentException;
use DateTime;

/**
 * @property Read $owner
 */
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
                $list = $list
                    ->setDataQueryParam('Versioned.mode', 'stage')
                    ->setDataQueryParam('Versioned.stage', $mode);
                break;
            case 'archive':
                if (empty($args['Versioning']['ArchiveDate'])) {
                    throw new InvalidArgumentException(sprintf(
                        'You must provide a Date parameter when using the "%s" mode',
                        $mode
                    ));
                }
                $date = $args['Versioning']['ArchiveDate'];
                if (!$this->isValidDate($date)) {
                    throw new InvalidArgumentException(sprintf(
                        'Invalid date: "%s". Must be YYYY-MM-DD format'
                    ));
                }

                $list = $list
                    ->setDataQueryParam('Versioned.mode', 'archive')
                    ->setDataQueryParam('Versioned.date', $date);
                break;
            case 'latest_versions':
                $list = $list->setDataQueryParam('Versioned.mode', 'latest_versions');
                break;
            case 'status':
                if (empty($args['Versioning']['Status'])) {
                    throw new InvalidArgumentException(sprintf(
                        'You must provide a Status parameter when using the "%s" mode',
                        $mode
                    ));
                }

                // When querying by Status we need to ensure both stage / live tables are present
                $baseTable = $this->owner->getDataObjectInstance()->baseTable();
                $liveTable = $baseTable . '_Live';
                $statuses = $args['Versioning']['Status'];

                // If we need to search archived records, we need to manually join draft table
                if (in_array('archived', $statuses)) {
                    $list = $list
                        ->setDataQueryParam('Versioned.mode', 'latest_versions');
                    // Join a temporary alias BaseTable_Draft, renaming this on execution to BaseTable
                    // See Versioned::augmentSQL() For reference on this alias
                    $draftTable = $baseTable . '_Draft';
                    $list = $list
                        ->leftJoin(
                            $draftTable,
                            "\"{$baseTable}\".\"ID\" = \"{$draftTable}\".\"ID\""
                        );
                } else {
                    // Use draft as base query mode (base join live)
                    $draftTable = $baseTable;
                    $list = $list
                        ->setDataQueryParam('Versioned.mode', 'stage')
                        ->setDataQueryParam('Versioned.stage', Versioned::DRAFT);
                }

                // Always include live table
                $list = $list->leftJoin(
                    $liveTable,
                    "\"{$baseTable}\".\"ID\" = \"{$liveTable}\".\"ID\""
                );

                // Add all conditions
                $conditions = [];

                // Modified exist on both stages, but differ
                if (in_array('modified', $statuses)) {
                    $conditions[] = "\"{$liveTable}\".\"ID\" IS NOT NULL AND \"{$draftTable}\".\"ID\" IS NOT NULL"
                        . " AND \"{$draftTable}\".\"Version\" <> \"{$liveTable}\".\"Version\"";
                }

                // Is deleted and sent to archive
                if (in_array('archived', $statuses)) {
                    // Note: Include items staged for deletion for the time being, as these are effectively archived
                    // we could split this out into "staged for deletion" in the future
                    $conditions[] = "\"{$draftTable}\".\"ID\" IS NULL";
                }

                // Is on draft only
                if (in_array('draft', $statuses)) {
                    $conditions[] = "\"{$liveTable}\".\"ID\" IS NULL AND \"{$draftTable}\".\"ID\" IS NOT NULL";
                }

                // Validate that all statuses have been handled
                if (empty($conditions) || count($statuses) !== count($conditions)) {
                    throw new InvalidArgumentException("Invalid statuses provided");
                }
                $list = $list->whereAny(array_filter($conditions));
                break;
            default:
                throw new InvalidArgumentException("Unsupported read mode {$mode}");
        }
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
