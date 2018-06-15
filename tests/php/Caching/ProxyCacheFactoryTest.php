<?php

namespace SilverStripe\Versioned\Tests\Caching;

use SilverStripe\Dev\SapphireTest;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Simple\PhpFilesCache;

class ProxyCacheFactoryTest extends SapphireTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!PhpFilesAdapter::isSupported()) {
            $this->markTestSkipped("This test requires opcache enabled");
        }
    }

    public function testCreateFallback()
    {
        $factory = new ProxyCacheFactoryFake([
            'argOne' => 'one'
        ]);
        $result = $factory->create('dummy', ['argTwo' => 'two']);

        $this->assertTrue($result instanceof PhpFilesCache);
    }

    public function testCreateCustomContainer()
    {
        $factory = new ProxyCacheFactoryFake([
            'argOne' => 'one',
            'container' => ProxyCacheAdapterFake::class,
        ]);
        $result = $factory->create('dummy', []);
        $this->assertTrue($result instanceof ProxyCacheAdapterFake);

        $factory = new ProxyCacheFactoryFake([
            'argOne' => 'one',
        ]);
        $result = $factory->create('dummy', ['container' => ProxyCacheAdapterFake::class]);
        $this->assertTrue($result instanceof ProxyCacheAdapterFake);
    }

    public function testDisableContainer()
    {
        $factory = new ProxyCacheFactoryFake([
            'argOne' => 'one',
            'container' => ProxyCacheAdapterFake::class,
        ]);
        $result = $factory->create('dummy', ['disable-container' => true]);
        $this->assertTrue($result instanceof PhpFilesCache);
    }
}
