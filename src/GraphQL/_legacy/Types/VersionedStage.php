<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\EnumType;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\Versioned\Versioned;

if (!class_exists(TypeCreator::class)) {
    return;
}

/**
 * @deprecated 4.8..5.0 Use silverstripe/graphql:^4 functionality.
 */
class VersionedStage extends TypeCreator
{
    /**
     * @return EnumType
     */
    public function toType()
    {
        return new EnumType([
            'name' => 'VersionedStage',
            'description' => 'The stage to read from or write to',
            'values' => [
                'DRAFT' => [
                    'value' => Versioned::DRAFT,
                    'description' => 'The draft stage',
                ],
                'LIVE' => [
                    'value' => Versioned::LIVE,
                    'description' => 'The live stage',
                ],
            ]
        ]);
    }
}
