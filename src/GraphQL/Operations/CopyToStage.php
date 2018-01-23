<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use SilverStripe\Core\Extensible;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\MutationScaffolder;
use SilverStripe\GraphQL\Scaffolding\Traits\DataObjectTypeTrait;
use GraphQL\Type\Definition\InputObjectType;
use SilverStripe\Core\Injector\Injector;
use GraphQL\Type\Definition\Type;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;
use InvalidArgumentException;
/**
 * A generic "create" operation for a DataObject.
 */
class CopyToStage extends MutationScaffolder
{
    use DataObjectTypeTrait;
    use Extensible;

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
            function ($object, array $args, $context, $info) {
                $input = $args['Input'];
                $id = $input['ID'];
                $record = DataObject::get_by_id($this->dataObjectClass, $id);
                if (!$record) {
                    throw new InvalidArgumentException(sprintf(
                        'Record %s not found',
                        $id
                    ));
                }

                $to = $input['ToStage'];
                if (isset($input['FromVersion'])) {
                    $from = $input['FromVersion'];
                } else if(isset($input['FromStage'])) {
                    $from = $input['FromStage'];
                } else {
                    throw new InvalidArgumentException('You must provide either a FromStage or FromVersion argument');
                }
                $member = Member::singleton();
                $can = $to === Versioned::LIVE
                    ? $member->canPublish($context['currentUser'])
                    : $member->canEdit($context['currentUser']);

                if (!$can) {
                    throw new InvalidArgumentException(sprintf(
                        'Copying %s from %s to %s is not allowed',
                        $class,
                        $from,
                        $to
                    ));
                }

                $record->copyVersionToStage($from, $to);

                return $record;
            }
        );
    }

    /**
     * @param Manager $manager
     */
    public function addToManager(Manager $manager)
    {
        $manager->addType($this->generateInputType($manager));
        parent::addToManager($manager);
    }

    /**
     * @param Manager $manager
     * @return array
     */
    protected function createArgs(Manager $manager)
    {
        $args = [
            'Input' => Type::nonNull($manager->getType($this->inputTypeName())),
        ];
        $this->extend('updateArgs', $args, $manager);

        return $args;
    }

    /**
     * @param Manager $manager
     * @return InputObjectType
     */
    protected function generateInputType(Manager $manager)
    {
        return new InputObjectType([
            'name' => $this->inputTypeName(),
            'fields' => function () use ($manager) {
                $fields = [
                    'ID' => [
                        'type' => Type::nonNull(Type::id()),
                        'description' => 'The ID of the record to copy',
                    ],
                    'FromVersion' => [
                        'type' => Type::int(),
                        'description' => 'The source version number to copy.'
                    ],
                    'FromStage' => [
                        'type' => $manager->getType('VersionedStage'),
                        'description' => 'The source stage to copy',
                    ],
                    'ToStage' => [
                        'type' => Type::nonNull($manager->getType('VersionedStage')),
                        'description' => 'The destination stage to copy to',
                    ],
                ];
                $instance = $this->getDataObjectInstance();

                // Setup default input args.. Placeholder!
                $schema = Injector::inst()->get(DataObjectSchema::class);
                $db = $schema->fieldSpecs($this->dataObjectClass);

                unset($db['ID']);

                foreach ($db as $dbFieldName => $dbFieldType) {
                    /** @var DBField $result */
                    $result = $instance->obj($dbFieldName);
                    // Skip complex fields, e.g. composite, as that would require scaffolding a new input type.
                    if (!$result->isInternalGraphQLType()) {
                        continue;
                    }
                    $arr = [
                        'type' => $result->getGraphQLType($manager),
                    ];
                    $fields[$dbFieldName] = $arr;
                }

                return $fields;
            },
        ]);
    }

    /**
     * @return string
     */
    protected function inputTypeName()
    {
        return $this->typeName().'CopyToStageInputType';
    }
}
