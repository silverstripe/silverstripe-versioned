<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Tests\VersionedNestedTest\PrimaryObject;
use SilverStripe\Versioned\Tests\VersionedNestedTest\ColumnObject;
use SilverStripe\Versioned\Tests\VersionedNestedTest\GroupObject;
use SilverStripe\Versioned\Tests\VersionedNestedTest\ChildObject;
use SilverStripe\Versioned\Versioned;

class VersionedNestedTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'VersionedNestedTest.yml';

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
        ColumnObject::class => [
            Versioned::class,
        ],
        GroupObject::class => [
            Versioned::class,
        ],
        ChildObject::class => [
            Versioned::class,
        ],
    ];

    /**
     * @param string $class
     * @param string $identifier
     * @param bool $delete
     * @throws ValidationException
     * @dataProvider objectsProvider
     */
    public function testStageDiffersRecursive(string $class, string $identifier, bool $delete): void
    {
        /** @var PrimaryObject $primaryItem */
        $primaryItem = $this->objFromFixture(PrimaryObject::class, 'primary-object-1');
        $primaryItem->publishRecursive();

        $this->assertFalse($primaryItem->stagesDifferRecursive());

        $record = $this->objFromFixture($class, $identifier);

        if ($delete) {
            $record->delete();
        } else {
            $record->Title = 'New Title';
            $record->write();
        }

        $this->assertTrue($primaryItem->stagesDifferRecursive());
    }

    public function objectsProvider(): array
    {
        return [
            [PrimaryObject::class, 'primary-object-1', false],
            [ColumnObject::class, 'column-1', false],
            [GroupObject::class, 'group-1', false],
            [ChildObject::class, 'child-object-1', false],
            [ColumnObject::class, 'column-1', true],
            [GroupObject::class, 'group-1', true],
            [ChildObject::class, 'child-object-1', true],
        ];
    }
}
