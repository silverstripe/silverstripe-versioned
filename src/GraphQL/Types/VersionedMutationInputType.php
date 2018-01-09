<?php

namespace SilverStripe\Versioned\GraphQL\Types;

use SilverStripe\GraphQL\TypeCreator;

class VersionedMutationInputType extends TypeCreator
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
            'name' => 'MutationVersioning'
        ];
    }

    /**
     * @return array
     */
    public function fields()
    {
        return [
            'Mode' => [
                'type' => new VersionedMutationInputType(),
            ],
        ];
    }
}