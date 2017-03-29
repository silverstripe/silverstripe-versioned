<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Tests\ManyManyThroughListTest;
use SilverStripe\Versioned\Versioned;

/**
 * @see ManyManyThroughListTest
 */
class VersionedManyManyThroughListTest extends SapphireTest
{
    protected static $fixture_file = 'VersionedManyManyThroughListTest.yml';

    protected static $extra_dataobjects = [
        VersionedManyManyThroughListTest\VersionedItem::class,
        VersionedManyManyThroughListTest\VersionedJoinObject::class,
        VersionedManyManyThroughListTest\VersionedObject::class,
    ];

    public function setUp()
    {
        parent::setUp();
        DataObject::reset();
    }

    public function tearDown()
    {
        DataObject::reset();
        parent::tearDown();
    }


    public function testPublishing()
    {
        /** @var VersionedManyManyThroughListTest\VersionedObject */
        $draftParent = $this->objFromFixture(VersionedManyManyThroughListTest\VersionedObject::class, 'parent1');
        $draftParent->publishRecursive();

        // Modify draft stage
        $item1 = $draftParent->Items()->filter(['Title' => 'versioned item 1'])->first();
        $item1->Title = 'new versioned item 1';
        $item1->getJoin()->Title = 'new versioned join 1';
        $item1->write(false, false, false, true); // Write joined components
        $draftParent->Title = 'new versioned title';
        $draftParent->write();

        // Check owned objects on stage
        $draftOwnedObjects = $draftParent->findOwned(true);
        $this->assertDOSEquals(
            [
                ['Title' => 'new versioned join 1'],
                ['Title' => 'versioned join 2'],
                ['Title' => 'new versioned item 1'],
                ['Title' => 'versioned item 2'],
            ],
            $draftOwnedObjects
        );

        // Check live record is still old values
        // This tests that both the join table and many_many tables
        // inherit the necessary query parameters from the parent object.
        /** @var VersionedManyManyThroughListTest\VersionedObject $liveParent */
        $liveParent = Versioned::get_by_stage(
            VersionedManyManyThroughListTest\VersionedObject::class,
            Versioned::LIVE
        )->byID($draftParent->ID);
        $liveOwnedObjects = $liveParent->findOwned(true);
        $this->assertDOSEquals(
            [
                ['Title' => 'versioned join 1'],
                ['Title' => 'versioned join 2'],
                ['Title' => 'versioned item 1'],
                ['Title' => 'versioned item 2'],
            ],
            $liveOwnedObjects
        );

        // Publish draft changes
        $draftParent->publishRecursive();
        $liveParent = Versioned::get_by_stage(
            VersionedManyManyThroughListTest\VersionedObject::class,
            Versioned::LIVE
        )->byID($draftParent->ID);
        $liveOwnedObjects = $liveParent->findOwned(true);
        $this->assertDOSEquals(
            [
                ['Title' => 'new versioned join 1'],
                ['Title' => 'versioned join 2'],
                ['Title' => 'new versioned item 1'],
                ['Title' => 'versioned item 2'],
            ],
            $liveOwnedObjects
        );
    }
}
