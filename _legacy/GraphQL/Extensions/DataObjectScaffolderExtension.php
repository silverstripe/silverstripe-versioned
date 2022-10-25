<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\Dev\Deprecation;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\DataObjectScaffolder;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\GraphQL\Operations\ReadVersions;
use SilverStripe\Versioned\Versioned;

// GraphQL dependency is optional in versioned,
// and legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(Manager::class)) {
    return;
}

/**
 * @deprecated 1.8.0 Use the latest version of graphql instead
 */
class DataObjectScaffolderExtension extends Extension
{
    /**
     * Adds the "Version" and "Versions" fields to any dataobject that has the Versioned extension.
     * @param Manager $manager
     */
    public function __construct()
    {
        Deprecation::notice('1.8.0', 'Use the latest version of graphql instead', Deprecation::SCOPE_CLASS);
    }

    public function onBeforeAddToManager(Manager $manager)
    {
        /* @var DataObjectScaffolder $owner */
        $owner = $this->owner;
        $memberType = StaticSchema::inst()->typeNameForDataObject(Member::class);
        $instance = $owner->getDataObjectInstance();
        $class = $owner->getDataObjectClass();
        if (!$instance->hasExtension(Versioned::class)) {
            return;
        }
        /* @var ObjectType $rawType */
        $rawType = $owner->scaffold($manager);

        $versionName = $this->createVersionedTypeName($class);
        $coreFieldsFn = $rawType->config['fields'];
        // Create the "version" type for this dataobject. Takes the original fields
        // and augments them with the Versioned_Version specific fields

        $versionType = new ObjectType([
            'name' => $versionName,
            'fields' => function () use ($coreFieldsFn, $manager, $memberType) {
                $coreFields = $coreFieldsFn();
                $versionFields = [
                    'Author' => [
                        'type' => $manager->getType($memberType),
                        'resolve' => function ($obj) {
                            return $obj->Author();
                        }
                    ],
                    'Publisher' => [
                        'type' => $manager->getType($memberType),
                        'resolve' => function ($obj) {
                            return $obj->Publisher();
                        }
                    ],
                    'Published' => [
                        'type' => Type::boolean(),
                        'resolve' => function ($obj) {
                            return $obj->WasPublished;
                        }
                    ],
                    'Draft' => [
                        'type' => Type::boolean(),
                        'resolve' => function ($obj) {
                            return $obj->WasDraft;
                        }
                    ],
                    'Deleted' => [
                        'type' => Type::boolean(),
                        'resolve' => function ($obj) {
                            return $obj->WasDeleted;
                        }
                    ],
                    'LiveVersion' => [
                        'type' => Type::boolean(),
                        'resolve' => function ($obj) {
                            return $obj->isLiveVersion();
                        }
                    ],
                    'LatestDraftVersion' => [
                        'type' => Type::boolean(),
                        'resolve' => function ($obj) {
                            return $obj->isLatestDraftVersion();
                        }
                    ],
                ];
                // Remove this recursive madness.
                unset($coreFields[StaticSchema::inst()->formatField('Versions')]);

                $versionFields = StaticSchema::inst()->formatKeys($versionFields);

                return array_merge($coreFields, $versionFields);
            }
        ]);

        $manager->addType($versionType, $versionName);

        list ($version, $versions) = StaticSchema::inst()->formatFields(['Version', 'Versions']);
        // With the version type in the manager now, add the versioning fields to the dataobject type
        $owner
            ->addFields([$version])
            ->nestedQuery($versions, ReadVersions::create($class, $versionName));
    }

    /**
     * @param string $class
     * @return string
     */
    protected function createVersionedTypeName($class)
    {
        return StaticSchema::inst()->typeNameForDataObject($class).'Version';
    }
}
