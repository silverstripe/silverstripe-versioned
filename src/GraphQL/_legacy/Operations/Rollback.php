<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use InvalidArgumentException;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\OperationResolver;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

if (!class_exists(MutationScaffolder::class)) {
    return;
}

/**
 * Scaffolds a "rollback recursive" operation for DataObjects.
 *
 * rollback[TypeName](ID!, ToVersion!)
 */
class Rollback extends MutationScaffolder implements OperationResolver
{
    /**
     * CreateOperationScaffolder constructor.
     *
     * @param string $dataObjectClass
     */
    public function __construct($dataObjectClass)
    {
        parent::__construct(null, null, $this, $dataObjectClass);
    }

    /**
     * @return string
     */
    public function getName()
    {
        $name = parent::getName();
        if ($name) {
            return $name;
        }

        $typeName = $this->getTypeName();

        return 'rollback' . ucfirst($typeName);
    }

    /**
     * @param Manager $manager
     * @return array
     */
    public function createDefaultArgs(Manager $manager)
    {
        return [
            'ID' => [
                'type' => Type::nonNull(Type::id()),
                'description' => 'The object ID that needs to be rolled back'
            ],
            'ToVersion' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'The version of the object that should be rolled back to'
            ],
        ];
    }

    /**
     * Invoked by the Executor class to resolve this mutation / query
     * @see Executor
     *
     * @param mixed $object
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return mixed
     */
    public function resolve($object, array $args, $context, ResolveInfo $info)
    {
        // Get the args
        $id = $args['ID'];
        $rollbackVersion = $args['ToVersion'];

        // Pull the latest version of the record
        /** @var Versioned|DataObject $record */
        $record = Versioned::get_latest_version($this->getDataObjectClass(), $id);

        // Assert permission
        $user = $context['currentUser'];
        if (!$record->canEdit($user)) {
            throw new InvalidArgumentException('Current user does not have permission to roll back this resource');
        }

        // Perform the rollback
        $record = $record->rollbackRecursive($rollbackVersion);

        return $record;
    }
}
