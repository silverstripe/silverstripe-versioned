<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\SS_List;
use SilverStripe\Versioned\GraphQL\Types\VersionedQueryInputType;

class Read extends Extension
{
    public function updateList(SS_List $list, $args)
    {
        // Filter list by stage
    }

    public function updateArgs($args)
    {
        $args['Versioning'] = [
            'type' => // need manager here to getType('VersionedQueryInputType')
        ];
    }
}