<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use Exception;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\ListQueryScaffolder;
use SilverStripe\ORM\DataObject;
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
            /** @var DataObject|Versioned $object */
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

            // Get all versions
            return $object->Versions();
        };
        $operationName = 'read' . ucfirst($versionTypeName);
        parent::__construct($operationName, $versionTypeName, $resolver);
    }
}
