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
                $list = $list->setDataQueryParam('Versioned.mode', 'stage')
                             ->setDataQueryParam('Versioned.stage', $mode);
                break;
            case Versioned::DRAFT:
                $newList = $list->setDataQueryParam('Versioned.mode', 'stage')
                             ->setDataQueryParam('Versioned.stage', 'Stage');
                $list = $newList;
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