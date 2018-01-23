<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Util\ScaffoldingUtil;
use SilverStripe\Versioned\GraphQL\Operations\ReadVersions;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Security\Member;

class SchemaScaffolderExtension extends Extension
{
    public function onBeforeAddToManager(Manager $manager)
    {
        /* @var SchemaScaffolder $owner */
        $owner = $this->owner;
        $needsMember = false;
        $memberType = ScaffoldingUtil::typeNameForDataObject(Member::class);

        foreach($owner->getTypes() as $dataObjectScaffold) {
            $instance = $dataObjectScaffold->getDataObjectInstance();
            $class = $dataObjectScaffold->getDataObjectClass();
            if ($instance->hasExtension(Versioned::class)) {
                $needsMember = true;
                /* @var ObjectType $rawType */
                $rawType = $dataObjectScaffold->scaffold($manager);

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
                            ]
                        ];

                        return array_merge($coreFields, $versionFields);
                    }
                ]);
                $manager->addType($versionType, $versionName);

                // With the version type in the manager now, add the versioning fields to the dataobject type
                $dataObjectScaffold
                    ->addFields(['Version'])
                    ->nestedQuery('Versions', new ReadVersions($class, $versionName));
            }
        }

        // The versioned type doesn't benefit from dependency sniffing because it's not a DataObject,
        // so the Member type has to be added explicitly.
        if ($needsMember) {
            $memberType = ScaffoldingUtil::typeNameForDataObject(Member::class);
            if (!$manager->hasType($memberType)) {
                $owner->type(Member::class);
            }
        }
    }

    /**
     * @param string $class
     * @return string
     */
    protected function createVersionedTypeName($class)
    {
        return ScaffoldingUtil::typeNameForDataObject($class).'Version';
    }

}