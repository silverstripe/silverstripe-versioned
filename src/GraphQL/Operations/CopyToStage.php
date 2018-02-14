<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use GraphQL\Type\Definition\Type;
use InvalidArgumentException;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Interfaces\ResolverInterface;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

if (!class_exists(MutationScaffolder::class)) {
    return;
}

/**
 * A generic "create" operation for a DataObject.
 */
class CopyToStage extends MutationScaffolder implements ResolverInterface
{
    use DataObjectTypeTrait;

    /**
     * CreateOperationScaffolder constructor.
     *
     * @param string $dataObjectClass
     */
    public function __construct($dataObjectClass)
    {
        $this->dataObjectClass = $dataObjectClass;
        parent::__construct(
            'copy'.ucfirst($this->typeName()).'ToStage',
            $this->typeName(),
            $this
        );
    }

    protected function createDefaultArgs(Manager $manager)
    {
        return [
            'Input' => Type::nonNull($manager->getType('CopyToStageInputType')),
        ];
    }

    public function resolve($object, $args, $context, $info)
    {
        $input = $args['Input'];
        $id = $input['ID'];
        $to = $input['ToStage'];
        /** @var Versioned|DataObject $record */
        $record = null;
        if (isset($input['FromVersion'])) {
            $from = $input['FromVersion'];
            $record = Versioned::get_version($this->dataObjectClass, $id, $from);
        } elseif (isset($input['FromStage'])) {
            $from = $input['FromStage'];
            $record = Versioned::get_by_stage($this->dataObjectClass, $from)->byID($id);
        } else {
            throw new InvalidArgumentException('You must provide either a FromStage or FromVersion argument');
        }
        if (!$record) {
            throw new InvalidArgumentException("Record {$id} not found");
        }

        // Permission check object
        $can = $to === Versioned::LIVE
            ? $record->canPublish($context['currentUser'])
            : $record->canEdit($context['currentUser']);
        if (!$can) {
            throw new InvalidArgumentException(sprintf(
                'Copying %s from %s to %s is not allowed',
                $this->typeName(),
                $from,
                $to
            ));
        }

        /** @var DataObject|Versioned $record */
        $record->copyVersionToStage($from, $to);
        return $record;
    }
}
