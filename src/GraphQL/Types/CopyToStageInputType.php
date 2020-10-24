<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\TypeCreator;

if (!class_exists(TypeCreator::class)) {
    return;
}

class CopyToStageInputType extends TypeCreator
{
    /**
     * @var bool
     */
    protected $inputObject = true;

    /**
     * @return array
     */
    public function attributes()
    {
        return [
            'name' => 'CopyToStageInputType'
        ];
    }

    /**
     * @return array
     */
    public function fields()
    {
        $id = StaticSchema::inst()->formatField('ID');
        $fromVersion = StaticSchema::inst()->formatField('FromVersion');
        $fromStage = StaticSchema::inst()->formatField('FromStage');
        $toStage = StaticSchema::inst()->formatField('ToStage');
        return [
            $id => [
                'type' => Type::nonNull(Type::id()),
                'description' => 'The ID of the record to copy',
            ],
            $fromVersion => [
                'type' => Type::int(),
                'description' => 'The source version number to copy.'
            ],
            $fromStage => [
                'type' => $this->manager->getType('VersionedStage'),
                'description' => 'The source stage to copy',
            ],
            $toStage => [
                'type' => Type::nonNull($this->manager->getType('VersionedStage')),
                'description' => 'The destination stage to copy to',
            ],
        ];
    }
}
