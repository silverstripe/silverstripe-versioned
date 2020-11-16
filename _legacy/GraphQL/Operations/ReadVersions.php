<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\GraphQL\OperationResolver;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\ListQueryScaffolder;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

// GraphQL dependency is optional in versioned,
// and legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(ListQueryScaffolder::class)) {
    return;
}

/**
 * Scaffolds a generic read operation for DataObjects.
 *
 * @deprecated 4.8..5.0 Use silverstripe/graphql:^4 functionality.
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
        $this->setDataObjectClass($dataObjectClass);
        $operationName = 'read' . ucfirst($versionTypeName);

        parent::__construct($operationName, $versionTypeName, $this);

        // Allow clients to sort the versions list by Version ID
        $this->addArg('sort', 'VersionSortType');
    }

    public function resolve($object, array $args, $context, ResolveInfo $info)
    {
        /** @var DataObject|Versioned $object */
        if (!$object->hasExtension(Versioned::class)) {
            throw new Exception(sprintf(
                'Types using the %s query scaffolder must have the Versioned extension applied. (See %s)',
                __CLASS__,
                $this->getDataObjectClass()
            ));
        }
        if (!$object->canViewStage(Versioned::DRAFT, $context['currentUser'])) {
            throw new Exception(sprintf(
                'Cannot view versions on %s',
                $this->getDataObjectClass()
            ));
        }

        // Get all versions
        $list = $object->VersionsList();

        $sort = $args['sort']['version'] ?? null;
        if ($sort) {
            $list = $list->sort('Version', $sort);
        }

        $this->extend('updateList', $list, $object, $args, $context, $info);

        return $list;
    }
}
