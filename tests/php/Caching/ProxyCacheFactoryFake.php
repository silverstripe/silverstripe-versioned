<?php

namespace SilverStripe\Versioned\Tests\Caching;

use SilverStripe\Versioned\Caching\ProxyCacheFactory;

class ProxyCacheFactoryFake extends ProxyCacheFactory
{
    protected function isAPCUSupported()
    {
        return false;
    }
}
