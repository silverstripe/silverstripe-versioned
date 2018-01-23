<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\Versioned\Versioned;
use GraphQL\Type\Definition\Type;

class VersionedReadInputType extends TypeCreator
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
            'name' => 'VersionedReadInputType'
        ];
    }

    /**
     * @return array
     */
    public function fields()
    {
        return [
            'Mode' => [
                'type' => $this->manager->getType('VersionedListQueryMode'),
                'defaultValue' => Versioned::LIVE,
            ],
            'ArchiveDate' => [
                'type' => Type::string(),
            ],
        ];
    }
}