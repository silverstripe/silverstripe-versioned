<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use SilverStripe\Dev\Deprecation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use InvalidArgumentException;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\OperationResolver;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

// GraphQL dependency is optional in versioned,
// and legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(MutationScaffolder::class)) {
    return;
}

/**
 * Scaffolds a "copy to stage" operation for DataObjects.
 *
 * copy[TypeName]ToStage(ID!, FromVersion!, FromStage!, ToStage!)
 *
 * @internal This is a low level API that might be removed in the future. Consider using the "rollback" mutation instead
 * @deprecated 1.8.0 Use the latest version of graphql instead
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
        Deprecation::notice('1.8.0', 'Use the latest version of graphql instead', Deprecation::SCOPE_CLASS);
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
        $input = $this->argName();
        return [
            $input => Type::nonNull($manager->getType('CopyToStageInputType')),
        ];
    }

    public function resolve($object, array $args, $context, ResolveInfo $info)
    {
        list($input) = StaticSchema::inst()->extractKeys(['Input'], $args);
        list($id, $to, $fromVersion, $fromStage) = StaticSchema::inst()->extractKeys(
            ['ID', 'ToStage', 'FromVersion', 'FromStage'],
            $input
        );
        /** @var Versioned|DataObject $record */
        $record = null;
        $from = null;
        if ($fromVersion) {
            $from = $fromVersion;
            $record = Versioned::get_version($this->getDataObjectClass(), $id, $from);
        } elseif ($fromStage) {
            $from = $fromStage;
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

    /**
     * @return string
     */
    private function argName()
    {
        return StaticSchema::inst()->formatField('Input');
    }
}
