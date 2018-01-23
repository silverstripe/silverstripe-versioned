<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\EnumType;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\Versioned\Versioned;

class VersionedItemQueryMode extends TypeCreator
{
    /**
     * @return EnumType
     */
    public function toType()
    {
        return new EnumType([
            'name' => 'VersionedItemQueryMode',
            'description' => 'The versioned mode to use',
            'values' => [
                'VERSION' => [
                    'value' => 'version',
                    'description' => 'Read a specific version'
                ],
                'DRAFT' => [
                    'value' => Versioned::DRAFT,
                    'description' => 'Read from the draft stage',
                ],
                'LIVE' => [
                    'value' => Versioned::LIVE,
                    'description' => 'Read from the live stage',
                ],
            ]
        ]);
    }
}