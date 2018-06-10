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
        $result = $cacheInstance->get('shared_key');
        $this->assertNull($result);
        $cacheInstance->set('shared_key', 'uncle');
        $this->assertEquals('uncle', $cacheInstance->get('shared_key'), 'Shared key is cached on LIVE');
        Versioned::set_stage(Versioned::DRAFT);
        $this->assertNull($cacheInstance->get('shared_key'), 'Shared key is not cached on DRAFT');
        $cacheInstance->set('shared_key', 'cheese');
        $cacheInstance->set('draft_key', 'bar');
        $this->assertEquals('cheese', $cacheInstance->get('shared_key'), 'Shared key has its own value on DRAFT');
        $this->assertEquals('bar', $cacheInstance->get('draft_key'), 'Draft-only key is cached on DRAFT');
        Versioned::set_stage(Versioned::LIVE);
        $this->assertNull($cacheInstance->get('draft_key'), 'Draft-only key is not on LIVE');
        $this->assertEquals('uncle', $cacheInstance->get('shared_key'), 'Value of shared key is preserved on LIVE');

        $cacheInstance->clear();
    }
}
