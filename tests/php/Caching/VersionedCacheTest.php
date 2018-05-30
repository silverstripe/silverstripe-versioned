<?php

namespace SilverStripe\Versioned\Tests\Caching;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Cache\CacheFactory;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;

class VersionedCacheTest extends SapphireTest
{
    public function testVersionedCache()
    {
        $cacheService = CacheInterface::class . '.mytest';
        Injector::inst()->load([
            $cacheService => [
                'factory' => CacheFactory::class,
                'constructor' => [
                    'namespace' => 'myapp',
                ],
            ],
        ]);
        /* @var CacheInterface $cacheInstance */
        $cacheInstance = Injector::inst()->get($cacheService);
        $cacheInstance->clear();

        Versioned::set_stage(Versioned::LIVE);
        $result = $cacheInstance->get('test');
        $this->assertNull($result);
        $cacheInstance->set('test', 'uncle');
        $this->assertEquals('uncle', $cacheInstance->get('test'), 'Value is cached on LIVE');
        Versioned::set_stage(Versioned::DRAFT);
        $this->assertNull($cacheInstance->get('test'), 'Value is not cached on DRAFT');
        $cacheInstance->set('test', 'cheese');
        $cacheInstance->set('foo', 'bar');
        $this->assertEquals('cheese', $cacheInstance->get('test'), 'Value of same key is cached on DRAFT');
        $this->assertEquals('bar', $cacheInstance->get('foo'), 'Value of different key is cached on DRAFT');
        Versioned::set_stage(Versioned::LIVE);
        $this->assertNull($cacheInstance->get('foo'), 'Value of different key is not on LIVE');
        $this->assertEquals('uncle', $cacheInstance->get('test'), 'Value of same key is preserved on LIVE');

        $cacheInstance->clear();
    }
}
