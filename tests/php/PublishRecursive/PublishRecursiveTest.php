<?php

namespace SilverStripe\Versioned\Tests\PublishRecursive;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

class PublishRecursiveTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        SlowDummyParent::class,
        SlowDummyObject::class,
    ];

    /**
     * @var array
     */
    protected static $required_extensions = [
        SlowDummyParent::class => [
            Versioned::class,
            RecursivePublishable::class,
        ],
        SlowDummyObject::class => [
            Versioned::class,
        ],
    ];

    /**
     * This test validates consistent timestamps of versions created by publish recursive in an object hierarchy
     * We expect that the top level object and all nested objects will end up
     * with the same timestamp of their latest version
     *
     * @throws ValidationException
     */
    public function testPublishRecursiveVersionTiming()
    {
        /** @var SlowDummyObject|Versioned $object */
        $object = SlowDummyObject::create();
        $object->Title = 'Slow Object';
        $object->write();

        /** @var SlowDummyParent|Versioned|RecursivePublishable $parent */
        $parent = SlowDummyParent::create();
        $parent->Title = 'Slow Parent';
        $parent->NestedObjectID = (int) $object->ID;
        $parent->write();
        $parent->publishRecursive();

        $versionsSuffix = '_Versions';
        $parentTable = SlowDummyParent::config()->get('table_name');
        $parentVersionedTable = $parentTable . $versionsSuffix;
        $objectTable = SlowDummyObject::config()->get('table_name');
        $objectVersionedTable = $objectTable . $versionsSuffix;
        $tables = [
            $parentVersionedTable => $parent->ID,
            $objectVersionedTable => $object->ID,
        ];

        $results = [];

        foreach ($tables as $table => $id) {
            $query = SQLSelect::create(
                '"LastEdited"',
                sprintf('"%s"', $table),
                ['"RecordID"' => $id],
                ['"Version"' => 'DESC'],
                [],
                [],
                1
            );

            $results[] = $query->execute()->value();
        }

        $parentEdit = array_shift($results);
        $objectEdit = array_shift($results);

        $this->assertNotEmpty($parentEdit);
        $this->assertNotEmpty($objectEdit);
        $this->assertEquals($parentEdit, $objectEdit);
    }
}
