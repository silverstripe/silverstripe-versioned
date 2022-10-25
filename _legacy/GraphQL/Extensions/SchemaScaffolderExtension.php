<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\Dev\Deprecation;
use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

// GraphQL dependency is optional in versioned,
// and legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(Manager::class)) {
    return;
}

/**
 * @deprecated 1.8.0 Use the latest version of graphql instead
 */
class SchemaScaffolderExtension extends Extension
{
    /**
     * If any types are using Versioned, make sure Member is added as a type. Because
     * the Versioned_Version object is just ViewableData, it has to be added explicitly.
     *
     * @param Manager $manager
     */
    public function __construct()
    {
        Deprecation::notice('1.8.0', 'Use the latest version of graphql instead', Deprecation::SCOPE_CLASS);
    }

    public function onBeforeAddToManager(Manager $manager)
    {
        $memberType = StaticSchema::inst()->typeNameForDataObject(Member::class);
        if ($manager->hasType($memberType)) {
            return;
        }

        /* @var SchemaScaffolder $owner */
        $owner = $this->owner;

        foreach ($owner->getTypes() as $scaffold) {
            if ($scaffold->getDataObjectInstance()->hasExtension(Versioned::class)) {
                $owner->type(Member::class);
                break;
            }
        }
    }
}
