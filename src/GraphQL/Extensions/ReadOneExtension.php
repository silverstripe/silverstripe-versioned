<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataList;
use SilverStripe\GraphQL\Manager;
use SilverStripe\Versioned\Versioned;
use InvalidArgumentException;
use DateTime;

class ReadOneExtension extends Extension
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
                $list = $list->setDataQueryParam('Versioned.mode', 'stage')
                    ->setDataQueryParam('Versioned.stage', $mode);
                break;
            case 'version':
                if (!isset($args['Version'])) {
                    throw new InvalidArgumentException(
                        'When using the "version" mode, you must specify a Version parameter'
                    );
                }
                $list = Versioned::get_version($list->dataClass(), $args['ID'], $args['Version']);
        }
    }

    /**
     * @param array $args
     * @param Manager $manager
     */
    public function updateArgs(&$args, Manager $manager)
    {
        $args['Versioning'] = [
            'type' => $manager->getType('VersionedReadOneInputType')
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