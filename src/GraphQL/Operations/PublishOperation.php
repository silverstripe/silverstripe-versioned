<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use SilverStripe\Core\Extensible;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\ValidationException;
use GraphQL\Type\Definition\Type;
use Exception;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

/**
 * Scaffolds a generic update operation for DataObjects.
 */
abstract class PublishOperation extends MutationScaffolder
{
    use DataObjectTypeTrait;
    use Extensible;

    /**
     * UpdateOperationScaffolder constructor.
     *
     * @param string $dataObjectClass
     */
    public function __construct($dataObjectClass)
    {
        $this->dataObjectClass = $dataObjectClass;

        parent::__construct(
            $this->createOperationName(),
            $this->typeName()
        );

        $this->setResolver(function ($object, array $args, $context, $info) {
            $oldMode = Versioned::get_reading_mode();
            $this->setReadingStage();
            $obj = DataObject::get_by_id($this->dataObjectClass, $args['ID']);
            if (!$obj) {
                throw new Exception(sprintf(
                    '%s with ID %s not found',
                    $this->dataObjectClass,
                    $args['ID']
                ));
            }

            if ($this->checkPermission($obj, $context['currentUser'])) {
                $results = $this->extend('augmentMutation', $obj, $args, $context, $info);
                // Extension points that return false should kill the write operation
                if (!in_array(false, $results, true)) {
                    try {
                        $this->doMutation($obj);
                    } catch (ValidationException $e) {
                        throw new Exception(
                            'Could not changed published state of %s. Got error: %s',
                            $this->dataObjectClass,
                            $e->getMessage()
                        );
                    }
                }
                Versioned::set_reading_mode($oldMode);
                return $obj;
            } else {
                throw new Exception(sprintf(
                    'Not allowed to change published state of this %s',
                    $this->dataObjectClass
                ));
            }
        });
    }

    /**
     * Use a generated Input type, and require an ID.
     *
     * @param Manager $manager
     * @return array
     */
    protected function createArgs(Manager $manager)
    {
        $args = [
            'ID' => [
                'type' => Type::nonNull(Type::id())
            ],
        ];
        $this->extend('updateArgs', $args, $manager);

        return $args;
    }

    abstract protected function checkPermission(DataObjectInterface $obj, Member $member);

    abstract protected function doMutation(DataObjectInterface $obj);

    abstract protected function createOperationName();

    abstract protected function setReadingStage();
}