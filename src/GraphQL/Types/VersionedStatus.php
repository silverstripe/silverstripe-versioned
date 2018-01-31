<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\EnumType;
use SilverStripe\GraphQL\TypeCreator;

if (!class_exists(TypeCreator::class)) {
    return;
}

class VersionedStatus extends TypeCreator
{
    /**
     * @return EnumType
     */
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
