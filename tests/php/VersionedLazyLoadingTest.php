<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Tests\DataObjectLazyLoadingTest;
use SilverStripe\Versioned\Tests\VersionedLazyLoadingTest\VersionedObject;
use SilverStripe\Versioned\Tests\VersionedLazyLoadingTest\VersionedSubObject;
use SilverStripe\Versioned\Tests\VersionedTest\Subclass;
use SilverStripe\Versioned\Tests\VersionedTest;
use SilverStripe\Versioned\Versioned;

/**
 * Based on code refactored from
 *
 * @see DataObjectLazyLoadingTest
 */
class VersionedLazyLoadingTest extends SapphireTest
{

    protected static $fixture_file = [
        'VersionedTest.yml'
    ];

    public static function getExtraDataObjects()
    {
        return array_merge(
            VersionedTest::$extra_dataobjects,
            [
                VersionedObject::class,
                VersionedSubObject::class,
            ]
        );
    }

    public function testLazyLoadedFieldsOnVersionedRecords()
    {
        // Save another record, sanity check that we're getting the right one
        $obj2 = new Subclass();
        $obj2->Name = "test2";
        $obj2->ExtraField = "foo2";
        $obj2->write();

        // Save the actual inspected record
        $obj1 = new Subclass();
        $obj1->Name = "test";
        $obj1->ExtraField = "foo";
        $obj1->write();
        $version1 = $obj1->Version;
        $obj1->Name = "test2";
        $obj1->ExtraField = "baz";
        $obj1->write();
        $version2 = $obj1->Version;


        $reloaded = Versioned::get_version(VersionedTest\Subclass::class, $obj1->ID, $version1);
        $this->assertEquals($reloaded->Name, 'test');
        $this->assertEquals($reloaded->ExtraField, 'foo');

        $reloaded = Versioned::get_version(VersionedTest\Subclass::class, $obj1->ID, $version2);
        $this->assertEquals($reloaded->Name, 'test2');
        $this->assertEquals($reloaded->ExtraField, 'baz');

        $reloaded = Versioned::get_latest_version(VersionedTest\Subclass::class, $obj1->ID);
        $this->assertEquals($reloaded->Version, $version2);
        $this->assertEquals($reloaded->Name, 'test2');
        $this->assertEquals($reloaded->ExtraField, 'baz');

        $allVersions = Versioned::get_all_versions(VersionedTest\Subclass::class, $obj1->ID);
        $this->assertEquals(2, $allVersions->count());
        $this->assertEquals($allVersions->first()->Version, $version1);
        $this->assertEquals($allVersions->first()->Name, 'test');
        $this->assertEquals($allVersions->first()->ExtraField, 'foo');
        $this->assertEquals($allVersions->last()->Version, $version2);
        $this->assertEquals($allVersions->last()->Name, 'test2');
        $this->assertEquals($allVersions->last()->ExtraField, 'baz');

        $obj1->delete();
    }

    public function testLazyLoadedFieldsDoNotReferenceVersionsTable()
    {
        // Save another record, sanity check that we're getting the right one
        $obj2 = new Subclass();
        $obj2->Name = "test2";
        $obj2->ExtraField = "foo2";
        $obj2->write();

        $obj1 = new VersionedSubObject();
        $obj1->PageName = "old-value";
        $obj1->ExtraField = "old-value";
        $obj1ID = $obj1->write();
        $obj1->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);

        $obj1 = VersionedSubObject::get()->byID($obj1ID);
        $this->assertEquals(
            'old-value',
            $obj1->PageName,
            "Correct value on base table when fetching base class"
        );
        $this->assertEquals(
            'old-value',
            $obj1->ExtraField,
            "Correct value on sub table when fetching base class"
        );

        $obj1 = VersionedObject::get()->byID($obj1ID);
        $this->assertEquals(
            'old-value',
            $obj1->PageName,
            "Correct value on base table when fetching sub class"
        );
        $this->assertEquals(
            'old-value',
            $obj1->ExtraField,
            "Correct value on sub table when fetching sub class"
        );

        // Force inconsistent state to test behaviour (shouldn't select from *_versions)
        DB::query(
            sprintf(
                "UPDATE \"VersionedLazy_DataObject_Versions\" SET \"PageName\" = 'versioned-value' " .
                "WHERE \"RecordID\" = %d",
                $obj1ID
            )
        );
        DB::query(
            sprintf(
                "UPDATE \"VersionedLazySub_DataObject_Versions\" SET \"ExtraField\" = 'versioned-value' " .
                "WHERE \"RecordID\" = %d",
                $obj1ID
            )
        );

        $obj1 = VersionedSubObject::get()->byID($obj1ID);
        $this->assertEquals(
            'old-value',
            $obj1->PageName,
            "Correct value on base table when fetching base class"
        );
        $this->assertEquals(
            'old-value',
            $obj1->ExtraField,
            "Correct value on sub table when fetching base class"
        );
        $obj1 = VersionedObject::get()->byID($obj1ID);
        $this->assertEquals(
            'old-value',
            $obj1->PageName,
            "Correct value on base table when fetching sub class"
        );
        $this->assertEquals(
            'old-value',
            $obj1->ExtraField,
            "Correct value on sub table when fetching sub class"
        );

        // Update live table only to test behaviour (shouldn't select from *_versions or stage)
        DB::query(
            sprintf(
                'UPDATE "VersionedLazy_DataObject_Live" SET "PageName" = \'live-value\' WHERE "ID" = %d',
                $obj1ID
            )
        );
        DB::query(
            sprintf(
                'UPDATE "VersionedLazySub_DataObject_Live" SET "ExtraField" = \'live-value\' WHERE "ID" = %d',
                $obj1ID
            )
        );

        Versioned::set_stage(Versioned::LIVE);
        $obj1 = VersionedObject::get()->byID($obj1ID);
        $this->assertEquals(
            'live-value',
            $obj1->PageName,
            "Correct value from base table when fetching base class on live stage"
        );
        $this->assertEquals(
            'live-value',
            $obj1->ExtraField,
            "Correct value from sub table when fetching base class on live stage"
        );
    }
}
