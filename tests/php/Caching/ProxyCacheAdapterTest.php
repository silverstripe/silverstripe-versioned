<?php

namespace SilverStripe\Versioned\Tests\Caching;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Dev\SapphireTest;

class ProxyCacheAdapterTest extends SapphireTest
{

    public function testItGetsFromPool()
    {
        $mock = $this->getMockCacheInterface();
        /* @var CacheInterface $mock */
        $mock
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo('test_get__fake'))
            ->willReturn('get__return');

        $cache = new ProxyCacheAdapterFake($mock);

        $result = $cache->get('test_get');
        $this->assertEquals('get__return', $result);
    }

    public function testItSetsToPool()
    {
        $mock = $this->getMockCacheInterface();
        /* @var CacheInterface $mock */
        $mock
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->equalTo('test_set__fake'),
                $this->equalTo('somevalue')
            )
            ->willReturn(true);

        $cache = new ProxyCacheAdapterFake($mock);

        $result = $cache->set('test_set', 'somevalue');
        $this->assertTrue($result);
    }

    public function testItHasPool()
    {
        $mock = $this->getMockCacheInterface();
        /* @var CacheInterface $mock */
        $mock
            ->expects($this->once())
            ->method('has')
            ->with(
                $this->equalTo('test_has__fake')
            )
            ->willReturn(true);

        $cache = new ProxyCacheAdapterFake($mock);

        $result = $cache->has('test_has');
        $this->assertTrue($result);
    }

    public function testItDeletesFromPool()
    {
        $mock = $this->getMockCacheInterface();
        /* @var CacheInterface $mock */
        $mock
            ->expects($this->once())
            ->method('delete')
            ->with(
                $this->equalTo('test_delete__fake')
            )
            ->willReturn(true);

        $cache = new ProxyCacheAdapterFake($mock);

        $result = $cache->delete('test_delete');
        $this->assertTrue($result);
    }

    public function testItClearsPool()
    {
        $mock = $this->getMockCacheInterface();
        /* @var CacheInterface $mock */
        $mock
            ->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $cache = new ProxyCacheAdapterFake($mock);

        $result = $cache->clear();
        $this->assertTrue($result);
    }

    public function testItGetsMultipleFromPool()
    {
        $mock = $this->getMockCacheInterface();
        /* @var CacheInterface $mock */
        $mock
            ->expects($this->once())
            ->method('getMultiple')
            ->with(
                $this->equalTo(['getOne__fake', 'getTwo__fake', 'getThree__fake'])
            )
            ->willReturn([
                'getOne__fake' => 'one',
                // getTwo deliberately omitted for fallback
                'getThree__fake' => 'three',
            ]);

        $cache = new ProxyCacheAdapterFake($mock);

        $result = $cache->getMultiple(['getOne', 'getTwo', 'getThree'], 'myDefault');

        $this->assertArrayHasKey('getOne', $result);
        $this->assertEquals('one', $result['getOne']);
        $this->assertArrayHasKey('getTwo', $result);
        $this->assertEquals('myDefault', $result['getTwo']);
        $this->assertArrayHasKey('getThree', $result);
        $this->assertEquals('three', $result['getThree']);
    }

    public function testItSetsMultipleToPool()
    {
        $mock = $this->getMockCacheInterface();
        /* @var CacheInterface $mock */
        $mock
            ->expects($this->once())
            ->method('setMultiple')
            ->with(
                $this->equalTo([
                    'setOne__fake' => 'one',
                    'setTwo__fake' => 'two',
                    'setThree__fake' => 'three',
                ])
            )
            ->willReturn(true);

        $cache = new ProxyCacheAdapterFake($mock);

        $result = $cache->setMultiple([
            'setOne' => 'one',
            'setTwo' => 'two',
            'setThree' => 'three',
        ]);
        $this->assertTrue($result);
    }

    public function testItDeletesMultipleToPool()
    {
        $mock = $this->getMockCacheInterface();
        /* @var CacheInterface $mock */
        $mock
            ->expects($this->once())
            ->method('deleteMultiple')
            ->with(
                $this->equalTo([
                    'deleteOne__fake',
                    'deleteTwo__fake',
                    'deleteThree__fake',
                ])
            )
            ->willReturn(true);

        $cache = new ProxyCacheAdapterFake($mock);

        $result = $cache->deleteMultiple(['deleteOne', 'deleteTwo', 'deleteThree']);
        $this->assertTrue($result);
    }

    protected function getMockCacheInterface()
    {
        $methods = ['get', 'set', 'has', 'delete', 'getMultiple', 'setMultiple', 'clear', 'deleteMultiple'];
        $mock = $this->getMockBuilder(CacheInterface::class)
            ->setMethods($methods)
            ->getMock();

        return $mock;
    }
}
