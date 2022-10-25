<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use SilverStripe\Dev\Deprecation;
use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\Versioned\Versioned;

// GraphQL dependency is optional in versioned,
// and legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(TypeCreator::class)) {
    return;
}

/**
 * @deprecated 1.8.0 Use the latest version of graphql instead
 */
class VersionedInputType extends TypeCreator
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
            'name' => 'VersionedInputType'
        ];
    }

    /**
     * @return array
     */
    public function fields()
    {
        return StaticSchema::inst()->formatKeys([
            'Mode' => [
                'type' => $this->manager->getType('VersionedQueryMode'),
                'defaultValue' => Versioned::DRAFT,
            ],
            'ArchiveDate' => [
                'type' => Type::string(),
                'description' => 'The date to use for archive '
            ],
            'Status' => [
                'type' => Type::listOf($this->manager->getType('VersionedStatus')),
                'description' => 'If mode is STATUS, specify which versioned statuses'
            ],
            'Version' => [
                'type' => Type::int(),
            ],
        ]);
    }
}
