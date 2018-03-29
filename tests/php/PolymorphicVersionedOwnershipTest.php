<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;

class PolymorphicVersionedOwnershipTest extends SapphireTest
{
    protected static $fixture_file = 'PolymorphicVersionedOwnershipTest.yml';

    protected static $extra_dataobjects = [
        PolymorphicVersionedOwnershipTest\PolymorphicOwned::class,
        PolymorphicVersionedOwnershipTest\PolymorphicOwner::class,
        PolymorphicVersionedOwnershipTest\PolymorphicIntermediary::class,
    ];

    public function testPublish()
    {
        /** @var PolymorphicVersionedOwnershipTest\PolymorphicOwner $owner1 */
        $owner1 = $this->objFromFixture(PolymorphicVersionedOwnershipTest\PolymorphicOwner::class, 'owner1');
        /** @var PolymorphicVersionedOwnershipTest\PolymorphicOwned $owned1 */
        $owned1 = $this->objFromFixture(PolymorphicVersionedOwnershipTest\PolymorphicOwned::class, 'owned1');
        /** @var PolymorphicVersionedOwnershipTest\PolymorphicIntermediary $join1 */
        $join1 = $this->objFromFixture(PolymorphicVersionedOwnershipTest\PolymorphicIntermediary::class, 'join1');

        $this->assertFalse($owned1->isPublished());
        $this->assertFalse($join1->isPublished());

        $owner1->publishRecursive();
        $this->assertTrue($owned1->isPublished());
        $this->assertTrue($join1->isPublished());
    }

    public function testFindOwned()
    {
        /** @var PolymorphicVersionedOwnershipTest\PolymorphicOwner $owner1 */
        $owner1 = $this->objFromFixture(PolymorphicVersionedOwnershipTest\PolymorphicOwner::class, 'owner1');
        $this->assertListEquals([
            [ 'Title' => 'Join 1'],
        ], $owner1->findOwned(false));
        $this->assertListEquals([
            [ 'Title' => 'Join 1'],
            [ 'Title' => 'Owned 1'],
        ], $owner1->findOwned(true));
    }

    public function testFindOwners()
    {
        /** @var PolymorphicVersionedOwnershipTest\PolymorphicOwned $owned1 */
        $owned1 = $this->objFromFixture(PolymorphicVersionedOwnershipTest\PolymorphicOwned::class, 'owned1');
        $this->assertListEquals([
            [ 'Title' => 'Join 1'],
        ], $owned1->findOwners(false));
        $this->assertListEquals([
            [ 'Title' => 'Owner 1'],
            [ 'Title' => 'Join 1'],
        ], $owned1->findOwners(true));
    }
}
