<?php

namespace SilverStripe\Versioned\Tests\Caching;

use SilverStripe\Versioned\Mode\Caching\ProxyCacheAdapter;

class ProxyCacheAdapterFake extends ProxyCacheAdapter
{
    protected function getKeyID($key)
    {
        return $key . '__fake';
    }
}
