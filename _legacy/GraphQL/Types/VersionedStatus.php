<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use SilverStripe\Dev\Deprecation;
use GraphQL\Type\Definition\EnumType;
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
class VersionedStatus extends TypeCreator
{
    /**
     * @return EnumType
     */
    public function __construct(Manager $manager = null)
    {
        Deprecation::notice('1.8.0', 'Use the latest version of graphql instead', Deprecation::SCOPE_CLASS);
        parent::__construct($manager);
    }

    public function toType()
    {
        return new EnumType([
            'name' => 'VersionedStatus',
            'description' => 'The stage to read from or write to',
            'values' => [
                'PUBLISHED' => [
                    'value' => 'published',
                    'description' => 'Only published records',
                ],
                'DRAFT' => [
                    'value' => 'draft',
                    'description' => 'Only draft records',
                ],
                'ARCHIVED' => [
                    'value' => 'archived',
                    'description' => 'Only records that have been archived',
                ],
                'MODIFIED' => [
                    'value' => 'modified',
                    'description' => 'Only records that have unpublished changes',
                ],
            ],
        ]);
    }
}
