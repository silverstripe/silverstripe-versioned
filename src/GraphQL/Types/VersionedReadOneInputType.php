<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\Versioned\Versioned;

class VersionedReadOneInputType extends TypeCreator
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
            'name' => 'VersionedOneReadInputType'
        ];
    }

    /**
     * @return array
     */
    public function fields()
    {
        return [
            'Mode' => [
                'type' => $this->manager->getType('VersionedItemQueryMode'),
                'defaultValue' => Versioned::DRAFT,
            ],
            'Version' => [
                'type' => Type::int(),
            ],
        ];
    }
}
