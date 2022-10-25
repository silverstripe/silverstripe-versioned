<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use SilverStripe\Dev\Deprecation;
use Exception;
use SilverStripe\Core\Injector\Injectable;
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
 * @deprecated 1.8.0 Use the latest version of graphql instead
 */
class ReadVersions extends ListQueryScaffolder implements OperationResolver
{
    use Injectable;
    /**
     * ReadOperationScaffolder constructor.
     *
     * @param string $dataObjectClass
     * @param string $versionTypeName
     */
    public function __construct($dataObjectClass, $versionTypeName)
    {
        Deprecation::notice('1.8.0', 'Use the latest version of graphql instead', Deprecation::SCOPE_CLASS);
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
