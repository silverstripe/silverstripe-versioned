<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Fake;

use GraphQL\Language\AST\OperationDefinitionNode;
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
        parent::__construct(
            new FieldDefinition(['name' => 'fake', 'type' => Type::string()]),
            new \ArrayObject,
            new ObjectType(['name' => 'fake']),
            [],
            new Schema([]),
            [],
            '',
            new OperationDefinitionNode([]),
            []
        );
    }
}
