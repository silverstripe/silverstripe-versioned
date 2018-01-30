<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\GraphQL\Manager;

class ReadOneExtension extends ReadExtension
{
    /**
     * @param array $args
     * @param Manager $manager
     */
    public function updateArgs(&$args, Manager $manager)
    {
        $args['Versioning'] = [
            'type' => $manager->getType('VersionedReadOneInputType')
        ];
    }
}
