<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;

/**
 * Tests cascade deletion of versioned objects
 */
class VersionedCascadeDeletesTest extends SapphireTest
{
    protected static $fixture_file = 'VersionedCascadeDeletesTest.yml';

    protected static $extra_dataobjects = [
        VersionedCascadeDeletesTest\ParentObject::class,
        VersionedCascadeDeletesTest\ChildObject::class,
        VersionedCascadeDeletesTest\GrandChildObject::class,
        VersionedCascadeDeletesTest\RelatedObject::class,
    ];

    protected function setUp()
    {
        parent::setUp();
        Versioned::set_stage(Versioned::DRAFT);
        $this->logInWithPermission('ADMIN');
    }

    /**
     * Test that unpublish of children triggers unpublish on live
     */
    public function testUnpublish()
    {
        /** @var VersionedCascadeDeletesTest\ParentObject $parent1 */
        $parent1 = $this->objFromFixture(VersionedCascadeDeletesTest\ParentObject::class, 'parent1');
        $parent1->publishRecursive();

        // Ensure all live objects are published
        $this->assertDOSEquals(
            [
                ['Title' => 'Child 1'],
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\ChildObject::class, Versioned::LIVE)
        );
        $this->assertDOSEquals(
            [
                ['Title' => 'Related 1'],
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\RelatedObject::class, Versioned::LIVE)
        );
        $this->assertDOSEquals(
            [
                ['Title' => 'Grandchild 1'],
                ['Title' => 'Grandchild 2'],
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\GrandChildObject::class, Versioned::LIVE)
        );

        // Ensure that unpublish removes ONLY cascade-delete from live, and not stage, nor non-cascade objects
        $parent1->doUnpublish();

        // Check live
        $this->assertDOSEquals(
            [],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\ChildObject::class, Versioned::LIVE)
        );
        $this->assertDOSEquals(
            [
                ['Title' => 'Related 1'], // owned, but not cascade_delete, so sticks around
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\RelatedObject::class, Versioned::LIVE)
        );
        $this->assertDOSEquals(
            [],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\GrandChildObject::class, Versioned::LIVE)
        );

        // Check stage
        $this->assertDOSEquals(
            [
                ['Title' => 'Child 1'],
                ['Title' => 'Child 2'],
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\ChildObject::class, Versioned::DRAFT)
        );
        $this->assertDOSEquals(
            [
                ['Title' => 'Related 1'],
                ['Title' => 'Related 2'],
                ['Title' => 'Related 3'],
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\RelatedObject::class, Versioned::DRAFT)
        );
        $this->assertDOSEquals(
            [
                ['Title' => 'Grandchild 1'],
                ['Title' => 'Grandchild 2'],
                ['Title' => 'Grandchild 3'],
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\GrandChildObject::class, Versioned::DRAFT)
        );
    }

    /**
     * Test that deleting from draft does not remove from live
     */
    public function testDeleteDraft()
    {
        /** @var VersionedCascadeDeletesTest\ParentObject $parent1 */
        $parent1 = $this->objFromFixture(VersionedCascadeDeletesTest\ParentObject::class, 'parent1');
        /** @var VersionedCascadeDeletesTest\ParentObject $parent2 */
        $parent2 = $this->objFromFixture(VersionedCascadeDeletesTest\ParentObject::class, 'parent2');
        $parent1->publishRecursive();
        $parent2->publishRecursive();
        $parent1->delete();

        // Check draft doesn't contain the deleted records
        $this->assertDOSEquals(
            [
                ['Title' => 'Child 2'],
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\ChildObject::class, Versioned::DRAFT)
        );
        $this->assertDOSEquals(
            [
                // None of these cascade delete
                ['Title' => 'Related 1'],
                ['Title' => 'Related 2'],
                ['Title' => 'Related 3'],
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\RelatedObject::class, Versioned::DRAFT)
        );
        $this->assertDOSEquals(
            [
                ['Title' => 'Grandchild 3'],
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\GrandChildObject::class, Versioned::DRAFT)
        );

        // Ensure all owned records persist on live
        $this->assertDOSEquals(
            [
                ['Title' => 'Child 1'],
                ['Title' => 'Child 2'],
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\ChildObject::class, Versioned::LIVE)
        );
        $this->assertDOSEquals(
            [
                ['Title' => 'Related 1'],
                ['Title' => 'Related 2'],
                // Note: related 3 has no owners
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\RelatedObject::class, Versioned::LIVE)
        );
        $this->assertDOSEquals(
            [
                ['Title' => 'Grandchild 1'],
                ['Title' => 'Grandchild 2'],
                ['Title' => 'Grandchild 3'],
            ],
            Versioned::get_by_stage(VersionedCascadeDeletesTest\GrandChildObject::class, Versioned::LIVE)
        );
    }
}
