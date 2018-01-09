<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Versioned\GraphQL\Types\VersionedMutationInputType;

class Create extends Extension
{
    public function onAfterMutation($obj, $args)
    {
        // Move record to appropriate stage
    }

    public function updateArgs($args)
    {
        $args['Versioning'] = [
            'type' => // need manager here to getType('VersionedMutationInputType')
        ];
    }
}