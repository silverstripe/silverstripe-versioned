<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use SilverStripe\Dev\Deprecation;
use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\TypeCreator;

// GraphQL dependency is optional in versioned,
// and legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(TypeCreator::class)) {
    return;
}

/**
 * @deprecated 1.8.0 Use the latest version of graphql instead
 */
class CopyToStageInputType extends TypeCreator
{
    /**
     * @var bool
     */
    protected $inputObject = true;

    /**
     * @return array
     */
    public function __construct(Manager $manager = null)
    {
        Deprecation::notice('1.8.0', 'Use the latest version of graphql instead', Deprecation::SCOPE_CLASS);
        parent::__construct($manager);
    }

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
        return StaticSchema::inst()->formatKeys([
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
        ]);
    }
}
