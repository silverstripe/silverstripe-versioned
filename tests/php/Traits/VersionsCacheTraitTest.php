<?php

namespace SilverStripe\Versioned\Tests\Traits;

use ReflectionProperty;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\SapphireTest;

class VersionsCacheTraitTest extends SapphireTest
{
    protected static $fixture_file = 'VersionsCacheTraitTest.yml';

    protected static $extra_dataobjects = [
        TestObject::class,
    ];

    public function testCacheEmptyByDefault(): void
    {
        $this->assertSame([], $this->getCache());
    }

    public static function providePrepopulateVersionsCache(): array
    {
        return [
            'all-no-publish' => [
                'ids' => [1, 2],
                'publishAll' => false,
                'expected' => [
                    '1_16dee0aa7e2522f8ee1a4dca5be76531' => [
                        [
                            'RecordID' => 1,
                            'Version' => 1,
                            'ClassName' => TestObject::class,
                            'WasPublished' => 0,
                            'WasDraft' => 1,
                        ],
                    ],
                    '2_16dee0aa7e2522f8ee1a4dca5be76531' => [
                        [
                            'RecordID' => 2,
                            'Version' => 1,
                            'ClassName' => TestObject::class,
                            'WasPublished' => 0,
                            'WasDraft' => 1,
                        ],
                    ],
                ],
            ],
            'all-publish' => [
                'ids' => [1, 2],
                'publishAll' => true,
                'expected' => [
                    '1_16dee0aa7e2522f8ee1a4dca5be76531' => [
                        [
                            'RecordID' => 1,
                            'Version' => 2,
                            'ClassName' => TestObject::class,
                            'WasPublished' => 1,
                            'WasDraft' => 1,
                        ],
                        [
                            'RecordID' => 1,
                            'Version' => 1,
                            'ClassName' => TestObject::class,
                            'WasPublished' => 0,
                            'WasDraft' => 1,
                        ],
                    ],
                    '2_16dee0aa7e2522f8ee1a4dca5be76531' => [
                        [
                            'RecordID' => 2,
                            'Version' => 2,
                            'ClassName' => TestObject::class,
                            'WasPublished' => 1,
                            'WasDraft' => 1,
                        ],
                        [
                            'RecordID' => 2,
                            'Version' => 1,
                            'ClassName' => TestObject::class,
                            'WasPublished' => 0,
                            'WasDraft' => 1,
                        ],
                    ],
                ],
            ],
            'single-publish' => [
                'ids' => [1],
                'publishAll' => true,
                'expected' => [
                    '1_16dee0aa7e2522f8ee1a4dca5be76531' => [
                        [
                            'RecordID' => 1,
                            'Version' => 2,
                            'ClassName' => TestObject::class,
                            'WasPublished' => 1,
                            'WasDraft' => 1,
                        ],
                        [
                            'RecordID' => 1,
                            'Version' => 1,
                            'ClassName' => TestObject::class,
                            'WasPublished' => 0,
                            'WasDraft' => 1,
                        ],
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('providePrepopulateVersionsCache')]
    public function testPrepopulateVersionsCache(array $ids, bool $publishAll, $expected): void
    {
        if ($publishAll) {
            TestObject::get()->each(fn($obj) => $obj->publishSingle());
        }
        TestTraitHolder::prepopulateVersionsCache(TestObject::class, $ids);
        $cached = $this->getCache();
        $actual = [];
        foreach ($cached as $key => $list) {
            $actual[$key] = [];
            foreach ($list->toArray() as $ver) {
                $actual[$key][] = [
                    'RecordID' => $ver->RecordID,
                    'Version' => $ver->Version,
                    'ClassName' => $ver->ClassName,
                    'WasPublished' => $ver->WasPublished,
                    'WasDraft' => $ver->WasDraft,
                ];
            }
        }
        $this->assertSame($expected, $actual);
    }


    protected function tearDown(): void
    {
        $this->resetCache();
        parent::tearDown();
    }

    private function getCache(): ?array
    {
        $refl = new ReflectionProperty(TestTraitHolder::class, 'versions_cache');
        $refl->setAccessible(true);
        return $refl->getValue();
    }

    private function resetCache(): ?array
    {
        $refl = new ReflectionProperty(TestTraitHolder::class, 'versions_cache');
        $refl->setAccessible(true);
        return $refl->setValue(null, []);
    }
}
