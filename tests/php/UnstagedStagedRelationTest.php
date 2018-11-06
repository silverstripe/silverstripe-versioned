<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Versioned\Tests\UnstagedStagedRelationTest\StagedObject;
use SilverStripe\Versioned\Tests\UnstagedStagedRelationTest\UnstagedObject;
use SilverStripe\Versioned\Tests\UnstagedStagedRelationTest\UnstagedStagedThroughObject;
use SilverStripe\Versioned\Versioned;

class UnstagedStagedRelationTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        StagedObject::class,
        UnstagedStagedThroughObject::class,
        UnstagedObject::class,
    ];

    protected $usesDatabase = true;

    public function testVersionedToStagedRelation()
    {
        Versioned::set_stage(Versioned::DRAFT);

        $stagedObject = StagedObject::create();
        $stagedObject->write();
        $stagedObject->publishRecursive();

        $this->mockWait();
        $unstagedObject = UnstagedObject::create();
        $unstagedObject->write();

        $this->mockWait();
        $unstagedObject->StagedObjects()->add($stagedObject);

        $this->mockWait();
        Versioned::set_stage(Versioned::LIVE);
        $unstagedObject->write(false, false, true);

        $this->assertCount(2, Versioned::get_all_versions(UnstagedObject::class, $unstagedObject->ID));
        $this->assertCount(0, Versioned::get_version(UnstagedObject::class, $unstagedObject->ID, 1)->StagedObjects());
        $this->assertCount(1, Versioned::get_version(UnstagedObject::class, $unstagedObject->ID, 2)->StagedObjects());
    }

    /**
     * @param int $seconds
     */
    protected function mockWait($seconds = 5)
    {
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + $seconds);
    }
}
