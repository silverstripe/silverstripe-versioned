<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\Versioned\Versioned;

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
                'defaultValue' => Versioned::DRAFT,
            ],
            'ArchiveDate' => [
                'type' => Type::string(),
                'description' => 'The date to use for archive '
            ],
            'Status' => [
                'type' => Type::listOf($this->manager->getType('VersionedStatus')),
                'description' => 'If mode is STATUS, specify which versioned statuses'
            ],
        ];
    }
}
