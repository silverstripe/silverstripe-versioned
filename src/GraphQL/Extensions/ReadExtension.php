<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Resolvers\ApplyVersionFilters;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Read;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\ReadOne;
use SilverStripe\ORM\DataList;
use SilverStripe\GraphQL\Manager;

/**
 * Decorator for either a Read or ReadOne query scaffolder
 *
 * @property Read|ReadOne $owner
 */
class ReadExtension extends Extension
{
    public function updateList(DataList &$list, $args)
    {
        if (!isset($args['Versioning'])) {
            return;
        }

        Injector::inst()->get(ApplyVersionFilters::class)
            ->applyToList($list, $args['Versioning']);
    }

    /**
     * @param array $args
     * @param Manager $manager
     */
    public function updateArgs(&$args, Manager $manager)
    {
        $args['Versioning'] = [
            'type' => $manager->getType('VersionedInputType'),
        ];
    }
}
