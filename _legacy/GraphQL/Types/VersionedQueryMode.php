<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\EnumType;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\Versioned\Versioned;

// GraphQL dependency is optional in versioned,
// and legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(TypeCreator::class)) {
    return;
}

/**
 * @deprecated 4.8..5.0 Use silverstripe/graphql:^4 functionality.
 */
class VersionedQueryMode extends TypeCreator
{
    /**
     * @return EnumType
     */
    public function toType()
    {
        return new EnumType([
            'name' => 'VersionedQueryMode',
            'description' => 'The versioned mode to use',
            'values' => $this->getValues()
        ]);
    }

    /**
     * @return array
     */
    protected function getValues()
    {
        return [
            'ARCHIVE' => [
                'value' => 'archive',
                'description' => 'Read from a specific date of the archive',
            ],
            'LATEST' => [
                'value' => 'latest_versions',
                'description' => 'Read the latest version',
            ],
            'ALL_VERSIONS' => [
                'value' => 'all_versions',
                'description' => 'Reads all versions',
            ],
            'DRAFT' => [
                'value' => Versioned::DRAFT,
                'description' => 'Read from the draft stage',
            ],
            'LIVE' => [
                'value' => Versioned::LIVE,
                'description' => 'Read from the live stage',
            ],
            'STATUS' => [
                'value' => 'status',
                'description' => 'Read only records with a specific status',
            ],
            'VERSION' => [
                'value' => 'version',
                'description' => 'Read a specific version',
            ],
        ];
    }
}
