<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Versioned\Versioned;

class VersionedInjected extends Versioned
{
    public function getIncludingDeleted($class, $filter = "", $sort = "")
    {
        return 'hello';
    }
}
