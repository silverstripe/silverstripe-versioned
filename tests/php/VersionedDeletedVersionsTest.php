<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest\CompanyOfficeLocation;
use SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest\CompanyPage;
use SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest\GalleryBlock;
use SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest\GalleryBlockItem;
use SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest\GalleryBlockPage;
use SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest\OfficeLocation;
use SilverStripe\Versioned\Versioned;

class VersionedDeletedVersionsTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        GalleryBlockPage::class,
        GalleryBlock::class,
        GalleryBlockItem::class,
        CompanyPage::class,
        OfficeLocation::class,
        CompanyOfficeLocation::class,
    ];

    protected $usesDatabase = true;

    public function testDeleteOwnedWithRepublishingOwner()
    {
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        /* @var GalleryBlockPage $page1 */
        $page1 = new GalleryBlockPage([
            'Title' => 'Page1v1',
        ]);
        $page1->write(); // v1

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->publishSingle(); // v2

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $block1 = new GalleryBlock([
            'Title' => 'GalleryBlock1v1',
            'GalleryBlockPageID' => $page1->ID,
        ]);
        $block1->write(); // v1
        $this->assertFalse($block1->isPublished());

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->Title = 'Page1v3';
        $page1->write(); // v3

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->publishRecursive(); // v4
        $this->assertTrue($block1->isPublished());
        $this->assertTrue($page1->isPublished());

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $block1->doArchive();
        $this->assertFalse($block1->isPublished());
        $this->assertFalse($block1->isOnDraft());

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->Title = 'Page1v5';
        $page1->publishRecursive(); // v5

        $this->assertCount(0, $page1->GalleryBlocks());
    }

    public function testArchive()
    {
        DBDatetime::set_mock_now(DBDatetime::now());
        /** @var GalleryBlockPage $page1 */
        $page1 = new GalleryBlockPage([
            'Title' => 'Page1v1'
        ]);
        $page1->write(); // v1

        // Publish v2 a few seconds later, which should create a new version on each stage
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->publishSingle(); // v2

        // Archive now
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->doArchive(); // v3

        // Check _Versioned table contains row with WasPublished = 1 WasDraft = 1 and WasDeleted = 1
        $table = $page1->baseTable() . '_Versions';
        $query = SQLSelect::create()
            ->addSelect(['"WasDeleted"', '"WasDraft"', '"WasPublished"', '"Version"'])
            ->setFrom("\"{$table}\"")
            ->addWhere([ "\"{$table}\".\"RecordID\" = ?" => $page1->ID ])
            ->addOrderBy("\"{$table}\".\"Version\" DESC");

        // Record has all the flags
        $version = $query->execute()->record();
        $this->assertTrue((bool)$version['WasDeleted']);
        $this->assertTrue((bool)$version['WasDraft']);
        $this->assertTrue((bool)$version['WasPublished']);
        $this->assertEquals(3, $version['Version']);
    }

    public function testUnpublish()
    {
        DBDatetime::set_mock_now(DBDatetime::now());
        /** @var GalleryBlockPage $page1 */
        $page1 = new GalleryBlockPage([
            'Title' => 'Page1v1'
        ]);
        $page1->write(); // v1

        // Publish v2 a few seconds later, which should create a new version on each stage
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->publishSingle(); // v2

        // Archive now
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->doUnpublish(); // v3

        // Check _Versioned table contains row with WasPublished = 1 WasDraft = 1 and WasDeleted = 1
        $table = $page1->baseTable() . '_Versions';
        $query = SQLSelect::create()
            ->addSelect(['"WasDeleted"', '"WasDraft"', '"WasPublished"', '"Version"'])
            ->setFrom("\"{$table}\"")
            ->addWhere([ "\"{$table}\".\"RecordID\" = ?" => $page1->ID ])
            ->addOrderBy("\"{$table}\".\"Version\" DESC");

        // Record has all the flags
        $version = $query->execute()->record();
        $this->assertTrue((bool)$version['WasDeleted']);
        $this->assertFalse((bool)$version['WasDraft']);
        $this->assertTrue((bool)$version['WasPublished']);
        $this->assertEquals(3, $version['Version']);
    }

    public function testDeleteOwnedWithoutRepublishingOwner()
    {
        DBDatetime::set_mock_now(DBDatetime::now());
        /** @var GalleryBlockPage $page1 */
        $page1 = new GalleryBlockPage([
            'Title' => 'Page1v1'
        ]);
        $page1->write(); // v1

        // Publish v2 a few seconds later, which should create a new version on each stage
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->publishSingle(); // v2
        /** @var GalleryBlockPage $page1Live */
        $page1 = Versioned::get_by_stage(GalleryBlockPage::class, Versioned::DRAFT)->byID($page1->ID);
        $page1Live = Versioned::get_by_stage(GalleryBlockPage::class, Versioned::LIVE)->byID($page1->ID);
        $this->assertEquals(2, $page1->Version);
        $this->assertEquals(2, $page1Live->Version);

        // Create a gallery block
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $block1 = new GalleryBlock([
            'Title' => 'GalleryBlock1v1',
            'GalleryBlockPageID' => $page1->ID,
        ]);
        $block1->write(); // v1
        $this->assertFalse($block1->isPublished());

        // Update draft version
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->Title = 'Page1v3';
        $page1->write(); // v3

        // Publish
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->publishRecursive(); // v4

        // Publish recursive creates new records all the way down
        $page1 = Versioned::get_by_stage(GalleryBlockPage::class, Versioned::DRAFT)->byID($page1->ID);
        $page1Live = Versioned::get_by_stage(GalleryBlockPage::class, Versioned::LIVE)->byID($page1->ID);
        $this->assertEquals(4, $page1->Version);
        $this->assertEquals(4, $page1Live->Version);
        $this->assertTrue($block1->isPublished());
        $this->assertTrue($page1->isPublished());

        // Delete block1
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $block1->doArchive();
        $this->assertFalse($block1->isPublished());
        $this->assertFalse($block1->isOnDraft());

        // If we go back in time 10 seconds before this deletion, the deleted block is still visible
        /** @var GalleryBlockPage $page1v3 */
        $page1v3 = Versioned::get_version(GalleryBlockPage::class, $page1->ID, 3);
        $this->assertEquals(3, $page1v3->Version);
        $this->assertNotNull($page1v3);
        $this->assertCount(1, $page1v3->GalleryBlocks());
        $this->assertEquals('GalleryBlock1v1', $page1v3->GalleryBlocks()->first()->Title);
    }

    public function testDeleteNestedOwnedWithoutRepublishingOwner()
    {
        DBDatetime::set_mock_now(DBDatetime::now());
        /* @var GalleryBlockPage|Versioned|RecursivePublishable $page1 */
        $page1 = new GalleryBlockPage([
            'Title' => 'Page1v1'
        ]);
        $page1->write(); // v1

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->publishRecursive(); // v2

        // Add block with 2 items
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $block1 = new GalleryBlock([
            'Title' => 'GalleryBlock1v1',
            'GalleryBlockPageID' => $page1->ID,
        ]);
        $block1->write(); // v3
        $this->assertFalse($block1->isPublished());

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        /* @var GalleryBlockItem $item1 */
        $item1 = new GalleryBlockItem([
            'Title' => 'GalleryBlockItem1v1',
            'GalleryBlockID' => $block1->ID,
        ]);
        $item1->write();

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        /* @var GalleryBlockItem $item2 */
        $item2 = new GalleryBlockItem([
            'Title' => 'GalleryBlockItem2v1',
            'GalleryBlockID' => $block1->ID,
        ]);
        $item2->write();

        // Publish the page with attached items
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->Title = 'Page1v3';
        $page1->write(); // v3

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $page1->publishRecursive(); // v4
        $liveVersion = Versioned::get_versionnumber_by_stage(GalleryBlockPage::class, Versioned::LIVE, $page1->ID, false);
        $this->assertEquals(4, $liveVersion);

        $this->assertTrue($block1->isPublished());
        $this->assertTrue($item1->isPublished());
        $this->assertTrue($item2->isPublished());

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $item2->doArchive();
        $this->assertFalse($item2->isPublished());
        $this->assertFalse($item2->isOnDraft());

        // Get page1 v2 again from before we did the archive
        /** @var GalleryBlockPage $page1v4 */
        $page1v4 = Versioned::get_version(GalleryBlockPage::class, $page1->ID, 4);
        $this->assertNotNull($page1v4);
        $this->assertCount(1, $page1v4->GalleryBlocks());
        /** @var GalleryBlock $galleryBlock1 */
        $galleryBlock1 = $page1v4->GalleryBlocks()->first();
        $this->assertEquals('GalleryBlock1v1', $galleryBlock1->Title);
        $this->assertCount(2, $galleryBlock1->Items());
        $this->assertEquals(
            ['GalleryBlockItem1v1', 'GalleryBlockItem2v1'],
            $galleryBlock1->Items()->column('Title')
        );
    }

    public function testAddAssociationAfterRecordHasAlreadyBeenPublished()
    {
        DBDatetime::set_mock_now(DBDatetime::now());
        /* @var OfficeLocation $location1 */
        $location1 = new OfficeLocation([
            'Title' => 'OfficeLocation1v1'
        ]);
        $location1->write(); // v1

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $location1->publishRecursive(); // v2
        $this->assertTrue($location1->isPublished());

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        /* @var CompanyPage $companyPage1 */
        $companyPage1 = new CompanyPage([
            'Title' => 'CompanyPage1v1'
        ]);
        $companyPage1->write(); // v1

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $companyPage1->publishRecursive(); // v2

        // Reload from stage to get updated version
        $companyPage1 = Versioned::get_by_stage(CompanyPage::class, Versioned::DRAFT)
            ->byID($companyPage1->ID);
        $this->assertEquals(2, $companyPage1->Version);
        $this->assertTrue($companyPage1->isPublished());

        // Add locations
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $companyPage1->OfficeLocations()->add($location1);
        $companyPage1->Title = 'CompanyPage1v3';
        $companyPage1->write(); // v3

        // Publish recursively
        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $companyPage1->publishRecursive(); // v4
        $companyPage1 = Versioned::get_by_stage(CompanyPage::class, Versioned::DRAFT)
            ->byID($companyPage1->ID);
        $this->assertEquals(4, $companyPage1->Version);

        // Check office locations at current version
        $this->assertCount(1, $companyPage1->OfficeLocations());
        $this->assertEquals('OfficeLocation1v1', $companyPage1->OfficeLocations()->first()->Title);

        // Check office locations prior to being assigned
        /** @var CompanyPage $companyPageV2 */
        $companyPageV2 = Versioned::get_version(CompanyPage::class, $companyPage1->ID, 2);
        $this->assertCount(0, $companyPageV2->OfficeLocations());
    }

    public function testCanRestoreToDraft()
    {
        DBDatetime::set_mock_now(DBDatetime::now());
        /* @var OfficeLocation $location1 */
        $location1 = new OfficeLocation([
            'Title' => 'OfficeLocation1v1'
        ]);
        $location1->write(); // v1

        DBDatetime::set_mock_now(DBDatetime::now()->getTimestamp() + 10);
        $location1->doArchive();
        $this->assertTrue($location1->isArchived());
        $this->assertTrue($location1->canRestoreToDraft());

        $this->logOut();
        $this->assertFalse($location1->canRestoreToDraft());

        $this->logInWithPermission('SOME_PERMISSION');
        $this->assertFalse($location1->canRestoreToDraft());

        $this->logInWithPermission('ADMIN');
        $this->assertTrue($location1->canRestoreToDraft());
    }
}
