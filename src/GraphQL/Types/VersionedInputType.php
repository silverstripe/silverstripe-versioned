<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use GraphQL\Type\Definition\Type;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\TypeCreator;
use SilverStripe\Versioned\Versioned;

if (!class_exists(TypeCreator::class)) {
    return;
}

class VersionedInputType extends TypeCreator
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
            'name' => 'VersionedInputType'
        ];
    }

    /**
     * @return array
     */
    public function fields()
    {
        $mode = StaticSchema::inst()->formatField('Mode');
        $archiveDate = StaticSchema::inst()->formatField('ArchiveDate');
        $status = StaticSchema::inst()->formatField('Status');
        $version = StaticSchema::inst()->formatField('Version');

        return [
            $mode => [
                'type' => $this->manager->getType('VersionedQueryMode'),
                'defaultValue' => Versioned::DRAFT,
            ],
            $archiveDate => [
                'type' => Type::string(),
                'description' => 'The date to use for archive '
            ],
            $status => [
                'type' => Type::listOf($this->manager->getType('VersionedStatus')),
                'description' => 'If mode is STATUS, specify which versioned statuses'
            ],
            $version => [
                'type' => Type::int(),
            ],
        ];
    }
}
