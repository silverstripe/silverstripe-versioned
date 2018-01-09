<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Versioned\GraphQL\Types\VersionedMutationInputType;

class Update extends Extension
{
    public function onAfterMutation($obj, $args)
    {
        // Update record on appropriate stage
    }

    public function updateArgs($args)
    {
        $args['Versioning'] = [
            'type' => // need manager here to getType('VersionedQueryInputType')
        ];
    }
}