<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\Type;
use SilverStripe\Versioned\Versioned;

class VersionedReadOneInputType extends VersionedReadInputType
{
    /**
     * @var bool
     */
    protected $inputObject = true;

    /**
     * @return array
     */
    public function attributes()
    {
        return [
            'name' => 'VersionedReadOneInputType'
        ];
    }

    /**
     * @return array
     */
    public function fields()
    {
        return array_merge(
            parent::fields(),
            [
                'Mode' => [
                    'type' => $this->manager->getType('VersionedItemQueryMode'),
                    'defaultValue' => Versioned::DRAFT,
                ],
                'Version' => [
                    'type' => Type::int(),
                ],
            ]
        );
    }
}
