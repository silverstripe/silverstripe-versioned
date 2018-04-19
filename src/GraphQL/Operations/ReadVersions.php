<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\GraphQL\OperationResolver;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\ListQueryScaffolder;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

if (!class_exists(ListQueryScaffolder::class)) {
    return;
}

/**
 * Scaffolds a generic read operation for DataObjects.
 */
class ReadVersions extends ListQueryScaffolder implements OperationResolver
{
    /**
     * ReadOperationScaffolder constructor.
     *
     * @param string $dataObjectClass
     * @param string $versionTypeName
     */
    public function __construct($dataObjectClass, $versionTypeName)
    {
        $this->dataObjectClass = $dataObjectClass;
        $operationName = 'read' . ucfirst($versionTypeName);
        parent::__construct($operationName, $versionTypeName, $this);
    }

    public function resolve($object, array $args, $context, ResolveInfo $info)
    {
        /** @var DataObject|Versioned $object */
        if (!$object->hasExtension(Versioned::class)) {
            throw new Exception(sprintf(
                'Types using the %s query scaffolder must have the Versioned extension applied. (See %s)',
                __CLASS__,
                $this->dataObjectClass
            ));
        }
        if (!$object->canViewStage(Versioned::DRAFT, $context['currentUser'])) {
            throw new Exception(sprintf(
                'Cannot view versions on %s',
                $this->dataObjectClass
            ));
        }

        // Get all versions
        return $object->Versions();
    }
}
