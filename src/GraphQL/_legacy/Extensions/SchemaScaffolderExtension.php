<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

class SchemaScaffolderExtension extends Extension
{
    /**
     * If any types are using Versioned, make sure Member is added as a type. Because
     * the Versioned_Version object is just ViewableData, it has to be added explicitly.
     *
     * @param Manager $manager
     */
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
