<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\EnumType;
use SilverStripe\Versioned\Versioned;

class VersionedMutationModeType extends EnumType
{
    /**
     * VersionedMutationModeType constructor.
     * @param $config
     */
    public function __construct($config)
    {
        parent::__construct([
            'name' => 'VersionedMutationMode',
            'description' => 'The versioned mode to use',
            'values' => [
                'DRAFT' => [
                    'value' => Versioned::DRAFT,
                    'description' => 'Write to the draft stage',
                ],
                'LIVE' => [
                    'value' => Versioned::LIVE,
                    'description' => 'Write to the live stage',
                ],
            ]
        ]);
    }
}