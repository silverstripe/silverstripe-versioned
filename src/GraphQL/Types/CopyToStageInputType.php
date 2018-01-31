<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\Type;
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
        return [
            'ID' => [
                'type' => Type::nonNull(Type::id()),
                'description' => 'The ID of the record to copy',
            ],
            'FromVersion' => [
                'type' => Type::int(),
                'description' => 'The source version number to copy.'
            ],
            'FromStage' => [
                'type' => $this->manager->getType('VersionedStage'),
                'description' => 'The source stage to copy',
            ],
            'ToStage' => [
                'type' => Type::nonNull($this->manager->getType('VersionedStage')),
                'description' => 'The destination stage to copy to',
            ],
        ];
    }
}
