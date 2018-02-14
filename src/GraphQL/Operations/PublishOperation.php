<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use Exception;
use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ResolverInterface;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

if (!class_exists(MutationScaffolder::class)) {
    return;
}

/**
 * Scaffolds a generic update operation for DataObjects.
 */
abstract class PublishOperation extends MutationScaffolder implements ResolverInterface
{
    use DataObjectTypeTrait;

    /**
     * @param string $dataObjectClass
     */
    public function __construct($dataObjectClass)
    {
        $this->dataObjectClass = $dataObjectClass;
        parent::__construct(
            $this->createOperationName(),
            $this->typeName(),
            $this
        );
    }

    public function resolve($object, $args, $context, $info)
    {
        $obj = Versioned::get_by_stage($this->dataObjectClass, $this->getReadingStage())
            ->byID($args['ID']);
        if (!$obj) {
            throw new Exception(sprintf(
                '%s with ID %s not found',
                $this->dataObjectClass,
                $args['ID']
            ));
        }

        if (!$this->checkPermission($obj, $context['currentUser'])) {
            throw new Exception(sprintf(
                'Not allowed to change published state of this %s',
                $this->dataObjectClass
            ));
        }

        // Extension points that return false should kill the write operation
        $results = $this->extend('augmentMutation', $obj, $args, $context, $info);
        if (in_array(false, $results, true)) {
            return $obj;
        }

        try {
            DB::get_conn()->withTransaction(function () use ($obj) {
                $this->doMutation($obj);
            });
        } catch (ValidationException $e) {
            throw new Exception(
                'Could not changed published state of %s. Got error: %s',
                $this->dataObjectClass,
                $e->getMessage()
            );
        }
        return $obj;
    }

    /**
     * Use a generated Input type, and require an ID.
     *
     * @param Manager $manager
     * @return array
     */
    protected function createDefaultArgs(Manager $manager)
    {
        return [
            'ID' => [
                'type' => Type::nonNull(Type::id())
            ],
        ];
    }

    abstract protected function checkPermission(DataObjectInterface $obj, Member $member);

    abstract protected function doMutation(DataObjectInterface $obj);

    abstract protected function createOperationName();

    abstract protected function getReadingStage();
}
