<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\Versioned\Versioned;
use GraphQL\Type\Definition\Type;

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
                'defaultValue' => Versioned::LIVE,
            ],
            'Version' => [
                'type' => Type::int(),
            ],
        ];
    }
}