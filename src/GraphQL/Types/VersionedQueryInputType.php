<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use SilverStripe\GraphQL\TypeCreator;

class VersionedQueryInputType extends TypeCreator
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
            'name' => 'QueryVersioning'
        ];
    }

    /**
     * @return array
     */
    public function fields()
    {
        return [
            'Mode' => [
                'type' => new VersionedQueryInputType(),
            ],
            'Version' => Type::int(),
            'Date' => Type::string(),
        ];
    }
}