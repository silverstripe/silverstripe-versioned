<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Tests\RecursiveStagesServiceTest\ChildObject;
use SilverStripe\Versioned\Tests\RecursiveStagesServiceTest\ColumnObject;
use SilverStripe\Versioned\Tests\RecursiveStagesServiceTest\GroupObject;
use SilverStripe\Versioned\Tests\RecursiveStagesServiceTest\PrimaryObject;
use SilverStripe\Versioned\Versioned;

class RecursiveStagesServiceTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'RecursiveStagesServiceTest.yml';

    /**
     * @var string[]
     */
    protected static $extra_dataobjects = [
        PrimaryObject::class,
        ColumnObject::class,
        GroupObject::class,
        ChildObject::class,
    ];

    protected static $required_extensions = [
        PrimaryObject::class => [
            Versioned::class,
        ],
        GroupObject::class => [
            Versioned::class,
        ],
        ChildObject::class => [
            Versioned::class,
        ],
    ];

    public function testStageDiffersRecursiveWithInvalidObject(): void
    {
        Versioned::withVersionedMode(function (): void {
            Versioned::set_stage(Versioned::DRAFT);

            /** @var PrimaryObject|Versioned $primaryItem */
            $primaryItem = PrimaryObject::create();

            $this->assertFalse($primaryItem->stagesDifferRecursive(), 'We expect to see no changes on invalid object');
        });
    }

    /**
     * @dataProvider objectsProvider
     */
    public function testStageDiffersRecursive(string $class, string $identifier, bool $delete, bool $expected): void
    {
        Versioned::withVersionedMode(function () use ($class, $identifier, $delete, $expected): void {
            Versioned::set_stage(Versioned::DRAFT);

            /** @var PrimaryObject|Versioned $primaryObject */
            $primaryObject = $this->objFromFixture(PrimaryObject::class, 'primary-object-1');
            $primaryObject->publishRecursive();

            $this->assertFalse($primaryObject->stagesDifferRecursive(), 'We expect no changes to be present initially');

            // Fetch a specific record and make an edit
            $record = $this->objFromFixture($class, $identifier);

            if ($delete) {
                // Delete the record
                $record->delete();
            } else {
                // Update the record
                $record->Title .= '-updated';
                $record->write();
            }

            $this->assertEquals($expected, $primaryObject->stagesDifferRecursive(), 'We expect to see changes depending on the case');
        });
    }

    public function objectsProvider(): array
    {
        return [
            'primary object (versioned, update)' => [
                PrimaryObject::class,
                'primary-object-1',
                false,
                true,
            ],
            'column (non-versioned, update)' => [
                ColumnObject::class,
                'column-1',
                false,
                false,
            ],
            'column (non-versioned, delete)' => [
                ColumnObject::class,
                'column-1',
                true,
                false,
            ],
            'group (versioned, update)' => [
                GroupObject::class,
                'group-1',
                false,
                true,
            ],
            'group (versioned, delete)' => [
                GroupObject::class,
                'group-1',
                true,
                true,
            ],
            'child (versioned, update)' => [
                ChildObject::class,
                'child-object-1',
                false,
                true,
            ],
            'child (versioned, delete)' => [
                ChildObject::class,
                'child-object-1',
                true,
                true,
            ],
        ];
    }
}
