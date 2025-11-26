<?php

namespace SilverStripe\Versioned\Tests\Traits;

use ReflectionProperty;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;

class VersionNumberCacheTraitTest extends SapphireTest
{
    protected static $fixture_file = 'VersionNumberCacheTraitTest.yml';

    protected static $extra_dataobjects = [
        TestObject::class,
    ];

    protected function tearDown(): void
    {
        $this->resetCache();
        parent::tearDown();
    }

    public function testCacheNullByDefault(): void
    {
        $this->assertSame(null, $this->getCache());
    }

    public static function providePrepopulateVersionNumberCacheForStage(): array
    {
        return [
            'draft-all-no-publish' => [
                'stage' => Versioned::DRAFT,
                'ids' => [],
                'publishAll' => false,
                'expected' => [
                    TestObject::class => [
                        Versioned::DRAFT => [
                            '_complete' => true,
                            1 => 1,
                            2 => 1,
                        ]
                    ]
                ],
            ],
            'live-all-no-publish' => [
                'stage' => Versioned::LIVE,
                'ids' => [],
                'publishAll' => false,
                'expected' => [
                    TestObject::class => [
                        Versioned::LIVE => [
                            '_complete' => true,
                        ],
                    ]
                ],
            ],
            'draft-all-publish' => [
                'stage' => Versioned::DRAFT,
                'ids' => [],
                'publishAll' => true,
                'expected' => [
                    TestObject::class => [
                        Versioned::DRAFT => [
                            '_complete' => true,
                            1 => 2,
                            2 => 2,
                        ],
                    ],
                ],
            ],
            'live-all-publish' => [
                'stage' => Versioned::LIVE,
                'ids' => [],
                'publishAll' => true,
                'expected' => [
                    TestObject::class => [
                        Versioned::LIVE => [
                            '_complete' => true,
                            1 => 2,
                            2 => 2,
                        ],
                    ]
                ],
            ],
            'draft-single-no-publish' => [
                'stage' => Versioned::DRAFT,
                'ids' => [1],
                'publishAll' => false,
                'expected' => [
                    TestObject::class => [
                        Versioned::DRAFT => [
                            1 => 1,
                        ]
                    ]
                ],
            ],
            'live-single-no-publish' => [
                'stage' => Versioned::LIVE,
                'ids' => [1],
                'publishAll' => false,
                'expected' => [
                    TestObject::class => [
                        Versioned::LIVE => [
                            1 => 0,
                        ],
                    ]
                ],
            ],
            'draft-single-publish' => [
                'stage' => Versioned::DRAFT,
                'ids' => [1],
                'publishAll' => true,
                'expected' => [
                    TestObject::class => [
                        Versioned::DRAFT => [
                            1 => 2,
                        ],
                    ],
                ],
            ],
            'live-single-publish' => [
                'stage' => Versioned::LIVE,
                'ids' => [1],
                'publishAll' => true,
                'expected' => [
                    TestObject::class => [
                        Versioned::LIVE => [
                            1 => 2,
                        ],
                    ]
                ],
            ],
        ];
    }

    #[DataProvider('providePrepopulateVersionNumberCacheForStage')]
    public function testPrepopulateVersionNumberCacheForStage(
        string $stage,
        array $ids,
        bool $publishAll,
        array $expected
    ): void {
        if ($publishAll) {
            TestObject::get()->each(fn($obj) => $obj->publishSingle());
        }
        TestTraitHolder::prepopulateVersionNumberCacheForStage(TestObject::class, $stage, $ids);
        $this->assertSame($expected, $this->getCache());
    }

    private function getCache(): ?array
    {
        $refl = new ReflectionProperty(TestTraitHolder::class, 'cache_versionnumber');
        return $refl->getValue();
    }

    private function resetCache(): ?array
    {
        $refl = new ReflectionProperty(TestTraitHolder::class, 'cache_versionnumber');
        // The property unset by default, though getting its value will resolve to null
        // so this is good enough
        return $refl->setValue(null, null);
    }
}
