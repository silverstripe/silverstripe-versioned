<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\EnumType;
use SilverStripe\Versioned\Versioned;

class VersionedQueryModeType extends EnumType
{
    /**
     * VersionedQueryModeType constructor.
     * @param $config
     */
    public function __construct($config)
    {
        parent::__construct([
            'name' => 'VersionedQueryMode',
            'description' => 'The versioned mode to use',
            'values' => [
                'ARCHIVE' => [
                    'value' => 'archive',
                    'description' => 'Read from a specific date of the archive',
                ],
                'LATEST' => [
                    'value' => 'latest_versions',
                    'description' => 'Read the latest version'
                ],
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