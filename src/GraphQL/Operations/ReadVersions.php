<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use GraphQL\Type\Definition\Type;
use SebastianBergmann\Version;
use SilverStripe\ORM\DataList;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\UnionScaffolder;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\ListQueryScaffolder;
use SilverStripe\GraphQL\Scaffolding\Util\ScaffoldingUtil;
use SilverStripe\GraphQL\Manager;
use SilverStripe\Core\ClassInfo;
use Exception;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

/**
 * Scaffolds a generic read operation for DataObjects.
 */
class ReadVersions extends ListQueryScaffolder
{
    /**
     * ReadOperationScaffolder constructor.
     *
     * @param string $dataObjectClass
     * @param string $versionTypeName
     */
    public function __construct($dataObjectClass, $versionTypeName)
    {
        $resolver = function ($object, array $args, $context, $info) use ($dataObjectClass) {
            if (!$object->hasExtension(Versioned::class)) {
                throw new Exception(sprintf(
                    'Types using the %s query scaffolder must have the Versioned extension applied. (See %s)',
                    __CLASS__,
                    $dataObjectClass
                ));
            }
            if (!$object->canViewStage(Versioned::DRAFT, $context['currentUser'])) {
                throw new Exception(sprintf(
                    'Cannot view versions on %s',
                    $dataObjectClass
                ));
            }
            $oldMode = Versioned::get_reading_mode();
            Versioned::set_stage($args['Stage']);
            $versions = $object->Versions();
            Versioned::set_reading_mode($oldMode);

            return $versions;
        };
        $operationName = 'read' . ucfirst($versionTypeName);
        parent::__construct($operationName, $versionTypeName, $resolver);
    }

    /**
     * @param Manager $manager
     * @return array
     */
    protected function createArgs(Manager $manager)
    {
        $args = [
            'Stage' => [
                'type' => Type::nonNull($manager->getType('VersionedStage')),
                'defaultValue' => Versioned::LIVE,
            ]
        ];

        return $args;
    }

}
