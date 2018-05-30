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
 * A generic "create" operation for a DataObject.
 */
class CopyToStage extends MutationScaffolder implements OperationResolver
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

        return 'copy'.ucfirst($typeName).'ToStage';
    }

    protected function createDefaultArgs(Manager $manager)
    {
        return [
            'Input' => Type::nonNull($manager->getType('CopyToStageInputType')),
        ];
    }

    public function resolve($object, array $args, $context, ResolveInfo $info)
    {
        $input = $args['Input'];
        $id = $input['ID'];
        $to = $input['ToStage'];
        /** @var Versioned|DataObject $record */
        $record = null;
        if (isset($input['FromVersion'])) {
            $from = $input['FromVersion'];
            $record = Versioned::get_version($this->getDataObjectClass(), $id, $from);
        } elseif (isset($input['FromStage'])) {
            $from = $input['FromStage'];
            $record = Versioned::get_by_stage($this->getDataObjectClass(), $from)->byID($id);
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
                $this->getTypeName(),
                $from,
                $to
            ));
        }

        /** @var DataObject|Versioned $record */
        $record->copyVersionToStage($from, $to);
        return $record;
    }
}
