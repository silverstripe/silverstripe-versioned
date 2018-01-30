<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\EnumType;

class VersionedItemQueryMode extends VersionedListQueryMode
{
    /**
     * @return EnumType
     */
    public function toType()
    {
        return new EnumType([
            'name' => 'VersionedItemQueryMode',
            'description' => 'The versioned mode to use',
            'values' => $this->getValues(),
        ]);
    }

    protected function getValues()
    {
        return array_merge(
            parent::getValues(),
            [
                'VERSION' => [
                    'value' => 'version',
                    'description' => 'Read a specific version'
                ]
            ]
        );
    }
}
