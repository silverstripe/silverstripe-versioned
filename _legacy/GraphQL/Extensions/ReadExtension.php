<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\Dev\Deprecation;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\GraphQL\Resolvers\ApplyVersionFilters;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Read;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\ReadOne;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\ORM\DataList;
use SilverStripe\GraphQL\Manager;

// GraphQL dependency is optional in versioned,
// and legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(Manager::class)) {
    return;
}

/**
 * Decorator for either a Read or ReadOne query scaffolder
 *
 * @property Read|ReadOne $owner
 * @deprecated 1.8.0 Use the latest version of graphql instead
 */
class ReadExtension extends Extension
{
    public function __construct()
    {
        Deprecation::notice('1.8.0', 'Use the latest version of graphql instead', Deprecation::SCOPE_CLASS);
    }

    public function updateList(DataList &$list, $args)
    {
        if (!isset($args[$this->argName()])) {
            return;
        }

        Injector::inst()->get(ApplyVersionFilters::class)
            ->applyToList($list, $args[$this->argName()]);
    }

    /**
     * @param array $args
     * @param Manager $manager
     */
    public function updateArgs(&$args, Manager $manager)
    {
        $args[$this->argName()] = [
            'type' => $manager->getType('VersionedInputType'),
        ];
    }

    /**
     * @return string
     */
    private function argName()
    {
        return StaticSchema::inst()->formatField('Versioning');
    }
}
