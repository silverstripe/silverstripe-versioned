<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Fake;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

// GraphQL dependency is optional in versioned,
// and the follow implementation relies on existence of this class (in GraphQL v4)
if (!class_exists(ResolveInfo::class)) {
    return;
}

class FakeResolveInfo extends ResolveInfo
{
    public function __construct()
    {
        // webonyx/graphql-php v0.12
        if (!property_exists(__CLASS__, 'fieldDefinition')) {
            return;
        }
        // webonyx/graphql-php v14
        parent::__construct(
            FieldDefinition::create(['name' => 'fake', 'type' => Type::string()]),
            [],
            new ObjectType(['name' => 'fake']),
            [],
            new Schema([]),
            [],
            '',
            null,
            []
        );
    }
}
