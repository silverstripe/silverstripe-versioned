<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest\CompanyOfficeLocation;
use SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest\CompanyPage;
use SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest\GalleryBlock;
use SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest\GalleryBlockItem;
use SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest\GalleryBlockPage;
use SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest\OfficeLocation;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Versioned\RecursivePublishable;

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
        /* @var GalleryBlockPage|Versioned|RecursivePublishable $page1 */
        $page1 = new GalleryBlockPage([
            'Title' => 'Page1v1',
        ]);
        $page1->write();
        $page1->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);

        $block1 = new GalleryBlock([
            'Title' => 'GalleryBlock1v1',
            'GalleryBlockPageID' => $page1->ID,
        ]);
        $block1->write();
        $this->assertFalse($block1->isPublished());

        $page1->Title = 'Page1v2';
        $page1->publishRecursive();
        $this->assertTrue($block1->isPublished());
        $this->assertTrue($page1->isPublished());

        $block1->doArchive();
        $this->assertFalse($block1->isPublished());
        $this->assertFalse($block1->isOnDraft());

        $page1->Title = 'Page1v3';
        $page1->publishRecursive();

        $this->assertCount(0, $page1->GalleryBlocks());
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
        /* @var GalleryBlockPage|Versioned|RecursivePublishable $page1 */
        $page1 = new GalleryBlockPage([
            'Title' => 'Page1v1'
        ]);
        $page1->write();
        $page1->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);

        $block1 = new GalleryBlock([
            'Title' => 'GalleryBlock1v1',
            'GalleryBlockPageID' => $page1->ID,
        ]);
        $block1->write();
        $this->assertFalse($block1->isPublished());

        /* @var Versioned|RecursivePublishable $item1 */
        $item1 = new GalleryBlockItem([
            'Title' => 'GalleryBlockItem1v1',
            'GalleryBlockID' => $block1->ID,
        ]);
        $item1->write();
        /* @var Versioned|RecursivePublishable $item2 */
        $item2 = new GalleryBlockItem([
            'Title' => 'GalleryBlockItem2v1',
            'GalleryBlockID' => $block1->ID,
        ]);
        $item2->write();

        $page1->Title = 'Page1v2';
        $page1->publishRecursive();

        $this->assertTrue($block1->isPublished());
        $this->assertTrue($item1->isPublished());
        $this->assertTrue($item2->isPublished());

        $item2->doArchive();
        $this->assertFalse($item2->isPublished());
        $this->assertFalse($item2->isOnDraft());

        $page1 = Versioned::get_by_stage(GalleryBlockPage::class, Versioned::LIVE)->byID($page1->ID);
        $this->assertNotNull($page1);

        $this->assertCount(1, $page1->GalleryBlocks());
        $this->assertEquals('GalleryBlock1v1', $page1->GalleryBlocks()->first()->Title);
        $this->assertCount(2, $page1->GalleryBlocks()->first()->Items());
        $this->assertEquals(
            ['GalleryBlockItem1v1', 'GalleryBlockItem2v1'],
            $page->GalleryBlocks()->Items()->column('Title')
        );
    }

    public function testAddAssociationAfterRecordHasAlreadyBeenPublished()
    {
        /* @var Versioned|RecursivePublishable $location1 */
        $location1 = new OfficeLocation([
            'Title' => 'OfficeLocation1v1'
        ]);
        $location1->write();
        $location1->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);

        $this->assertTrue($location1->isPublished());

        /* @var Versioned|RecursivePublishable $companyPage1 */
        $companyPage1 = new CompanyPage([
            'Title' => 'CompanyPage1v1'
        ]);
        $companyPage1->write();
        $companyPage1->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        $this->assertEquals(1, $companyPage1->Version);
        $this->assertTrue($companyPage1->isPublished());

        $companyPage1->OfficeLocations()->add($location1);
        $companyPage1->Title = 'CompanyPage1v2';
        $companyPage1->publishRecursive();
        $this->assertEquals(2, $companyPage1->Version, 'Incorrect version');

        $this->assertCount(1, $companyPage1->OfficeLocations());
        $this->assertEquals('OfficeLocation1v1', $companyPage1->OfficeLocations()->first()->Title);

        $previousVersion = Versioned::get_version(CompanyPage::class, $companyPage1->ID, 1);
        $this->assertCount(0, $previousVersion->OfficeLocations());
    }
}
