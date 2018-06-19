<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Versioned\Tests\VersionedOwnershipTest\Attachment;
use SilverStripe\Versioned\Tests\VersionedOwnershipTest\Banner;
use SilverStripe\Versioned\Tests\VersionedOwnershipTest\Image;
use SilverStripe\Versioned\Tests\VersionedOwnershipTest\Related;
use SilverStripe\Versioned\Tests\VersionedOwnershipTest\RelatedMany;
use SilverStripe\Versioned\Tests\VersionedOwnershipTest\TestPage;
use SilverStripe\Versioned\Tests\VersionedTest\Subclass;
use SilverStripe\Versioned\Versioned;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Dev\SapphireTest;
use DateTime;

/**
 * Tests ownership API of versioned DataObjects
 */
class VersionedOwnershipTest extends SapphireTest
{

    protected static $extra_dataobjects = [
        VersionedOwnershipTest\TestObject::class,
        VersionedOwnershipTest\Subclass::class,
        VersionedOwnershipTest\Related::class,
        VersionedOwnershipTest\Attachment::class,
        VersionedOwnershipTest\RelatedMany::class,
        VersionedOwnershipTest\TestPage::class,
        VersionedOwnershipTest\Banner::class,
        VersionedOwnershipTest\Image::class,
        VersionedOwnershipTest\CustomRelation::class,
        VersionedOwnershipTest\UnversionedOwner::class,
        VersionedOwnershipTest\OwnedByUnversioned::class,
    ];

    protected static $fixture_file = 'VersionedOwnershipTest.yml';

    public function setUp()
    {
        parent::setUp();

        Versioned::set_stage(Versioned::DRAFT);

        // Automatically publish any object named *_published
        foreach ($this->getFixtureFactory()->getFixtures() as $class => $fixtures) {
            foreach ($fixtures as $name => $id) {
                if (stripos($name, '_published') !== false) {
                    /** @var Versioned|DataObject $object */
                    $object = DataObject::get($class)->byID($id);
                    $object->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
                }
            }
        }
    }

    /**
     * Virtual "sleep" that doesn't actually slow execution, only advances DBDateTime::now()
     *
     * @param int $minutes
     */
    protected function sleep($minutes)
    {
        $now = DBDatetime::now();
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $now->getValue());
        $date->modify("+{$minutes} minutes");
        DBDatetime::set_mock_now($date->format('Y-m-d H:i:s'));
    }

    /**
     * Test basic findOwned() in stage mode
     */
    public function testFindOwned()
    {
        /** @var VersionedOwnershipTest\Subclass $subclass1 */
        $subclass1 = $this->objFromFixture(VersionedOwnershipTest\Subclass::class, 'subclass1_published');
        $this->assertListEquals(
            [
                ['Title' => 'Related 1'],
                ['Title' => 'Attachment 1'],
                ['Title' => 'Attachment 2'],
                ['Title' => 'Attachment 5'],
                ['Title' => 'Related Many 1'],
                ['Title' => 'Related Many 2'],
                ['Title' => 'Related Many 3'],
            ],
            $subclass1->findOwned()
        );

        // Non-recursive search
        $this->assertListEquals(
            [
                ['Title' => 'Related 1'],
                ['Title' => 'Related Many 1'],
                ['Title' => 'Related Many 2'],
                ['Title' => 'Related Many 3'],
            ],
            $subclass1->findOwned(false)
        );

        // Search for unversioned parent
        /** @var VersionedOwnershipTest\UnversionedOwner $unversioned */
        $unversioned = $this->objFromFixture(VersionedOwnershipTest\UnversionedOwner::class, 'unversioned1');
        $this->assertListEquals(
            [
                ['Title' => 'Book 1'],
                ['Title' => 'Book 2'],
                ['Title' => 'Book 3'],
            ],
            $unversioned->findOwned()
        );

        /** @var VersionedOwnershipTest\Subclass $subclass2 */
        $subclass2 = $this->objFromFixture(VersionedOwnershipTest\Subclass::class, 'subclass2_published');
        $this->assertListEquals(
            [
                ['Title' => 'Related 2'],
                ['Title' => 'Attachment 3'],
                ['Title' => 'Attachment 4'],
                ['Title' => 'Attachment 5'],
                ['Title' => 'Related Many 4'],
            ],
            $subclass2->findOwned()
        );

        // Non-recursive search
        $this->assertListEquals(
            [
                ['Title' => 'Related 2'],
                ['Title' => 'Related Many 4'],
            ],
            $subclass2->findOwned(false)
        );

        /** @var VersionedOwnershipTest\Related $related1 */
        $related1 = $this->objFromFixture(VersionedOwnershipTest\Related::class, 'related1');
        $this->assertListEquals(
            [
                ['Title' => 'Attachment 1'],
                ['Title' => 'Attachment 2'],
                ['Title' => 'Attachment 5'],
            ],
            $related1->findOwned()
        );

        /** @var VersionedOwnershipTest\Related $related2 */
        $related2 = $this->objFromFixture(VersionedOwnershipTest\Related::class, 'related2_published');
        $this->assertListEquals(
            [
                ['Title' => 'Attachment 3'],
                ['Title' => 'Attachment 4'],
                ['Title' => 'Attachment 5'],
            ],
            $related2->findOwned()
        );
    }

    public function testHasOwned()
    {
        $this->assertFalse(Subclass::create()->hasOwned(), 'hasOwned returns false on unwritten objects');

        /** @var VersionedOwnershipTest\Subclass $subclass1 */
        $subclass1 = $this->objFromFixture(VersionedOwnershipTest\Subclass::class, 'subclass1_published');
        $this->assertTrue($subclass1->hasOwned());

        /** @var VersionedOwnershipTest\Related $related1 */
        $related1 = $this->objFromFixture(VersionedOwnershipTest\Related::class, 'related1');

        // Test when the list is empty
        $related1->Attachments()->removeAll();
        $this->assertFalse($related1->hasOwned(), 'hasOwned is false when relation is empty');

        $banner = $this->objFromFixture(Banner::class, 'banner1_published');
        $this->assertTrue($banner->hasOwned(), 'hasOwned is true when a has_one exists');
        $banner->ImageID = 0;
        $this->assertFalse($banner->hasOwned(), 'hasOwned is false when a has_one does not exist');
    }

    /**
     * Test findOwners
     */
    public function testFindOwners()
    {
        /** @var VersionedOwnershipTest\Attachment $attachment1 */
        $attachment1 = $this->objFromFixture(VersionedOwnershipTest\Attachment::class, 'attachment1');
        $this->assertListEquals(
            [
                ['Title' => 'Related 1'],
                ['Title' => 'Subclass 1'],
            ],
            $attachment1->findOwners()
        );

        // Non-recursive search
        $this->assertListEquals(
            [
                ['Title' => 'Related 1'],
            ],
            $attachment1->findOwners(false)
        );

        /** @var VersionedOwnershipTest\Attachment $attachment5 */
        $attachment5 = $this->objFromFixture(VersionedOwnershipTest\Attachment::class, 'attachment5_published');
        $this->assertListEquals(
            [
                ['Title' => 'Related 1'],
                ['Title' => 'Related 2'],
                ['Title' => 'Subclass 1'],
                ['Title' => 'Subclass 2'],
            ],
            $attachment5->findOwners()
        );

        // Non-recursive
        $this->assertListEquals(
            [
                ['Title' => 'Related 1'],
                ['Title' => 'Related 2'],
            ],
            $attachment5->findOwners(false)
        );

        /** @var VersionedOwnershipTest\Related $related1 */
        $related1 = $this->objFromFixture(VersionedOwnershipTest\Related::class, 'related1');
        $this->assertListEquals(
            [
                ['Title' => 'Subclass 1'],
            ],
            $related1->findOwners()
        );

        // Can find unversioned owners
        /** @var VersionedOwnershipTest\OwnedByUnversioned $image */
        $image = $this->objFromFixture(VersionedOwnershipTest\OwnedByUnversioned::class, 'book1');
        $this->assertListEquals(
            [
                [ 'Title' => 'Unversioned 1' ],
            ],
            $image->findOwners()
        );
    }

    /**
     * Test findOwners on Live stage
     */
    public function testFindOwnersLive()
    {
        // Modify a few records on stage
        $related2 = $this->objFromFixture(VersionedOwnershipTest\Related::class, 'related2_published');
        $related2->Title .= ' Modified';
        $related2->write();
        $attachment3 = $this->objFromFixture(VersionedOwnershipTest\Attachment::class, 'attachment3_published');
        $attachment3->Title .= ' Modified';
        $attachment3->write();
        $attachment4 = $this->objFromFixture(VersionedOwnershipTest\Attachment::class, 'attachment4_published');
        $attachment4->delete();
        $subclass2ID = $this->idFromFixture(VersionedOwnershipTest\Subclass::class, 'subclass2_published');

        // Check that stage record is ok
        /** @var VersionedOwnershipTest\Subclass $subclass2Stage */
        $subclass2Stage = Versioned::get_by_stage(VersionedOwnershipTest\Subclass::class, 'Stage')->byID($subclass2ID);
        $this->assertListEquals(
            [
                ['Title' => 'Related 2 Modified'],
                ['Title' => 'Attachment 3 Modified'],
                ['Title' => 'Attachment 5'],
                ['Title' => 'Related Many 4'],
            ],
            $subclass2Stage->findOwned()
        );

        // Non-recursive
        $this->assertListEquals(
            [
                ['Title' => 'Related 2 Modified'],
                ['Title' => 'Related Many 4'],
            ],
            $subclass2Stage->findOwned(false)
        );

        // Live records are unchanged
        /** @var VersionedOwnershipTest\Subclass $subclass2Live */
        $subclass2Live = Versioned::get_by_stage(VersionedOwnershipTest\Subclass::class, 'Live')->byID($subclass2ID);
        $this->assertListEquals(
            [
                ['Title' => 'Related 2'],
                ['Title' => 'Attachment 3'],
                ['Title' => 'Attachment 4'],
                ['Title' => 'Attachment 5'],
                ['Title' => 'Related Many 4'],
            ],
            $subclass2Live->findOwned()
        );

        // Test non-recursive
        $this->assertListEquals(
            [
                ['Title' => 'Related 2'],
                ['Title' => 'Related Many 4'],
            ],
            $subclass2Live->findOwned(false)
        );
    }

    /**
     * Test that objects are correctly published recursively
     */
    public function testRecursivePublish()
    {
        /** @var VersionedOwnershipTest\Subclass $parent */
        $parent = $this->objFromFixture(VersionedOwnershipTest\Subclass::class, 'subclass1_published');
        $parentID = $parent->ID;
        $banner1 = $this->objFromFixture(VersionedOwnershipTest\RelatedMany::class, 'relatedmany1_published');
        $banner2 = $this->objFromFixture(VersionedOwnershipTest\RelatedMany::class, 'relatedmany2_published');
        $banner2ID = $banner2->ID;

        // Modify, Add, and Delete banners on stage
        $banner1->Title = 'Renamed Banner 1';
        $banner1->write();

        $banner2->delete();

        $banner4 = new VersionedOwnershipTest\RelatedMany();
        $banner4->Title = 'New Banner';
        $parent->Banners()->add($banner4);

        // Check state of objects before publish
        $oldLiveBanners = [
            ['Title' => 'Related Many 1'],
            ['Title' => 'Related Many 2'], // Will be unlinked (but not deleted)
            // `Related Many 3` isn't published
        ];
        $newBanners = [
            ['Title' => 'Renamed Banner 1'], // Renamed
            ['Title' => 'Related Many 3'], // Published without changes
            ['Title' => 'New Banner'], // Created
        ];
        /** @var VersionedOwnershipTest\Subclass $parentDraft */
        $parentDraft = Versioned::get_by_stage(VersionedOwnershipTest\Subclass::class, Versioned::DRAFT)
            ->byID($parentID);
        $this->assertListEquals($newBanners, $parentDraft->Banners());
        /** @var VersionedOwnershipTest\Subclass $parentLive */
        $parentLive = Versioned::get_by_stage(VersionedOwnershipTest\Subclass::class, Versioned::LIVE)
            ->byID($parentID);
        $this->assertListEquals($oldLiveBanners, $parentLive->Banners());

        // On publishing of owner, all children should now be updated
        $now = DBDatetime::now();
        DBDatetime::set_mock_now($now); // Lock 'now' to predictable time
        $parent->publishRecursive();

        // Now check each object has the correct state
        $parentDraft = Versioned::get_by_stage(VersionedOwnershipTest\Subclass::class, Versioned::DRAFT)
            ->byID($parentID);
        $this->assertListEquals($newBanners, $parentDraft->Banners());
        $parentLive = Versioned::get_by_stage(VersionedOwnershipTest\Subclass::class, Versioned::LIVE)
            ->byID($parentID);
        $this->assertListEquals($newBanners, $parentLive->Banners());

        // Check that the deleted banner hasn't actually been deleted from the live stage,
        // but in fact has been unlinked.
        /** @var VersionedOwnershipTest\RelatedMany $banner2Live */
        $banner2Live = Versioned::get_by_stage(VersionedOwnershipTest\RelatedMany::class, Versioned::LIVE)
            ->byID($banner2ID);
        $this->assertEmpty($banner2Live->PageID);

        // Test that a changeset was created
        /** @var ChangeSet $changeset */
        $changeset = ChangeSet::get()->sort('"ChangeSet"."ID" DESC')->first();
        $this->assertNotEmpty($changeset);

        // Test that this changeset is inferred
        $this->assertTrue((bool)$changeset->IsInferred);
        $this->assertEquals(
            "Generated by publish of 'Subclass 1' at ".$now->Nice(),
            $changeset->getTitle()
        );

        // Test that this changeset contains all items
        $this->assertListContains(
            [
                [
                    'ObjectID' => $parent->ID,
                    'ObjectClass' => $parent->baseClass(),
                    'Added' => ChangeSetItem::EXPLICITLY
                ],
                [
                    'ObjectID' => $banner1->ID,
                    'ObjectClass' => $banner1->baseClass(),
                    'Added' => ChangeSetItem::IMPLICITLY
                ],
                [
                    'ObjectID' => $banner4->ID,
                    'ObjectClass' => $banner4->baseClass(),
                    'Added' => ChangeSetItem::IMPLICITLY
                ]
            ],
            $changeset->Changes()
        );

        // Objects that are unlinked should not need to be a part of the changeset
        $this->assertListNotContains(
            [[ 'ObjectID' => $banner2ID, 'ObjectClass' => $banner2->baseClass() ]],
            $changeset->Changes()
        );
    }

    /**
     * Test that unversioned objects that own versioned items can be published
     */
    public function testUnversionedPublish()
    {
        /** @var VersionedOwnershipTest\UnversionedOwner $unversioned1 */
        $unversioned1 = $this->objFromFixture(VersionedOwnershipTest\UnversionedOwner::class, 'unversioned1');
        /** @var VersionedOwnershipTest\OwnedByUnversioned $book1 */
        $book1 = $this->objFromFixture(VersionedOwnershipTest\OwnedByUnversioned::class, 'book1');
        /** @var VersionedOwnershipTest\OwnedByUnversioned $book2 */
        $book2 = $this->objFromFixture(VersionedOwnershipTest\OwnedByUnversioned::class, 'book2');
        /** @var VersionedOwnershipTest\OwnedByUnversioned $book3 */
        $book3 = $this->objFromFixture(VersionedOwnershipTest\OwnedByUnversioned::class, 'book3_published');

        // Remove book3 from draft
        $book3ID = $book3->ID;
        $book3->delete();

        // Check initial state
        $this->assertFalse($book1->isPublished());
        $this->assertFalse($book2->isPublished());
        $this->assertTrue($book3->isPublished()); // live-only not on draft

        // On publishing of owner, all children should now be updated
        $now = DBDatetime::now();
        DBDatetime::set_mock_now($now); // Lock 'now' to predictable time

        // Publish this object recursively
        $unversioned1->publishRecursive();

        // Check that all images were published
        $this->assertTrue($book1->isPublished());
        $this->assertTrue($book2->isPublished());
        $this->assertTrue($book3->isPublished()); // live-only not on draft

        // Check that the deleted banner hasn't actually been deleted from the live stage,
        // but in fact has been unlinked.
        /** @var VersionedOwnershipTest\OwnedByUnversioned $book3Live */
        $book3Live = Versioned::get_by_stage(VersionedOwnershipTest\OwnedByUnversioned::class, Versioned::LIVE)
            ->byID($book3ID);
        $this->assertEmpty($book3Live->ParentID);

        // Test that a changeset was created
        /** @var ChangeSet $changeset */
        $changeset = ChangeSet::get()->sort('"ChangeSet"."ID" DESC')->first();
        $this->assertNotEmpty($changeset);

        // Test that this changeset is inferred
        $this->assertTrue((bool)$changeset->IsInferred);
        $this->assertEquals(
            "Generated by publish of 'Unversioned 1' at ".$now->Nice(),
            $changeset->getTitle()
        );

        // Test that this changeset contains all items
        $this->assertListContains(
            [
                [
                    'ObjectID' => $unversioned1->ID,
                    'ObjectClass' => $unversioned1->baseClass(),
                    'Added' => ChangeSetItem::EXPLICITLY
                ],
                [
                    'ObjectID' => $book1->ID,
                    'ObjectClass' => $book1->baseClass(),
                    'Added' => ChangeSetItem::IMPLICITLY
                ],
                [
                    'ObjectID' => $book2->ID,
                    'ObjectClass' => $book2->baseClass(),
                    'Added' => ChangeSetItem::IMPLICITLY
                ]
            ],
            $changeset->Changes()
        );

        // Objects that are unlinked should not need to be a part of the changeset
        $this->assertListNotContains(
            [[ 'ObjectID' => $book3ID, 'ObjectClass' => $book3->baseClass() ]],
            $changeset->Changes()
        );
    }

    /**
     * Test that owning objects don't get unpublished when object is unpublished
     */
    public function testRecursiveUnpublish()
    {
        // Unsaved objects can't be unpublished
        $unsaved = new VersionedOwnershipTest\Subclass();
        $this->assertFalse($unsaved->doUnpublish());

        // Draft-only objects can't be unpublished
        /** @var VersionedOwnershipTest\RelatedMany $banner3Unpublished */
        $banner3Unpublished = $this->objFromFixture(VersionedOwnershipTest\RelatedMany::class, 'relatedmany3');
        $this->assertFalse($banner3Unpublished->doUnpublish());

        // First test: mid-level unpublish; no other objects are unpublished
        /** @var VersionedOwnershipTest\Related $related2 */
        $related2 = $this->objFromFixture(VersionedOwnershipTest\Related::class, 'related2_published');
        /** @var VersionedOwnershipTest\Attachment $attachment3 */
        $attachment3 = $this->objFromFixture(VersionedOwnershipTest\Attachment::class, 'attachment3_published');
        /** @var VersionedOwnershipTest\RelatedMany $relatedMany4 */
        $relatedMany4 = $this->objFromFixture(VersionedOwnershipTest\RelatedMany::class, 'relatedmany4_published');

        // Ensure that this object, and it's owned objects, are aware of published parents
        $this->assertTrue($attachment3->hasPublishedOwners());
        $this->assertTrue($related2->hasPublishedOwners());

        /** @var VersionedOwnershipTest\Related $related2 */
        $this->assertTrue($related2->doUnpublish());
        /** @var VersionedOwnershipTest\Subclass $subclass2 */
        $subclass2 = $this->objFromFixture(VersionedOwnershipTest\Subclass::class, 'subclass2_published');

        // After unpublish this should change
        $this->assertFalse($attachment3->hasPublishedOwners()); // Because owner is unpublished
        $this->assertFalse($related2->hasPublishedOwners()); // Because self is unpublished
        $this->assertFalse($subclass2->hasPublishedOwners()); // Because no owners

        /** @var VersionedOwnershipTest\Subclass $subclass2 */
        $this->assertTrue($subclass2->isPublished()); // Owner is not unpublished
        $this->assertTrue($attachment3->isPublished()); // Owned object is NOT unpublished
        $this->assertTrue($relatedMany4->isPublished()); // Owned object by owner is NOT unpublished

        // Second test: Re-publishing the owner should re-publish this item
        $subclass2->publishRecursive();
        $this->assertTrue($subclass2->isPublished());
        $this->assertTrue($related2->isPublished());
        $this->assertTrue($attachment3->isPublished());
    }

    public function testRecursiveArchive()
    {
        // When archiving an object owners are not affected

        /** @var VersionedOwnershipTest\Attachment $attachment3 */
        $attachment3 = $this->objFromFixture(VersionedOwnershipTest\Attachment::class, 'attachment3_published');
        $attachment3ID = $attachment3->ID;
        $this->assertTrue($attachment3->hasPublishedOwners()); // Warning should be shown
        $this->assertTrue($attachment3->doArchive());

        // This object is on neither stage nor live
        $stageAttachment = Versioned::get_by_stage(VersionedOwnershipTest\Attachment::class, Versioned::DRAFT)
            ->byID($attachment3ID);
        $liveAttachment = Versioned::get_by_stage(VersionedOwnershipTest\Attachment::class, Versioned::LIVE)
            ->byID($attachment3ID);
        $this->assertEmpty($stageAttachment);
        $this->assertEmpty($liveAttachment);

        // Owning object is not unpublished or archived
        /** @var VersionedOwnershipTest\Related $stageOwner */
        $stageOwner = $this->objFromFixture(VersionedOwnershipTest\Related::class, 'related2_published');
        $this->assertTrue($stageOwner->isOnDraft());
        $this->assertTrue($stageOwner->isPublished());

        // Bottom level owning object is also unaffected
        /** @var VersionedOwnershipTest\Subclass $stageTopOwner */
        $stageTopOwner = $this->objFromFixture(VersionedOwnershipTest\Subclass::class, 'subclass2_published');
        $this->assertTrue($stageTopOwner->isOnDraft());
        $this->assertTrue($stageTopOwner->isPublished());
    }

    public function testRecursiveRevertToLive()
    {
        /** @var VersionedOwnershipTest\Subclass $parent */
        $parent = $this->objFromFixture(VersionedOwnershipTest\Subclass::class, 'subclass1_published');
        $parentID = $parent->ID;
        $banner1 = $this->objFromFixture(VersionedOwnershipTest\RelatedMany::class, 'relatedmany1_published');
        $banner2 = $this->objFromFixture(VersionedOwnershipTest\RelatedMany::class, 'relatedmany2_published');

        // Modify, Add, and Delete banners on stage
        $banner1->Title = 'Renamed Banner 1';
        $banner1->write();

        $banner2->delete();

        $banner4 = new VersionedOwnershipTest\RelatedMany();
        $banner4->Title = 'New Banner';
        $banner4->write();
        $parent->Banners()->add($banner4);

        // Check state of objects before publish
        $liveBanners = [
            ['Title' => 'Related Many 1'],
            ['Title' => 'Related Many 2'],
        ];
        $modifiedBanners = [
            ['Title' => 'Renamed Banner 1'], // Renamed
            ['Title' => 'Related Many 3'], // Published without changes
            ['Title' => 'New Banner'], // Created
        ];
        /** @var VersionedOwnershipTest\Subclass $parentDraft */
        $parentDraft = Versioned::get_by_stage(VersionedOwnershipTest\Subclass::class, Versioned::DRAFT)
            ->byID($parentID);
        $this->assertListEquals($modifiedBanners, $parentDraft->Banners());
        /** @var VersionedOwnershipTest\Subclass $parentLive */
        $parentLive = Versioned::get_by_stage(VersionedOwnershipTest\Subclass::class, Versioned::LIVE)
            ->byID($parentID);
        $this->assertListEquals($liveBanners, $parentLive->Banners());

        // When reverting parent, all records should be put back on stage
        $this->assertTrue($parent->doRevertToLive());

        // Now check each object has the correct state
        $parentDraft = Versioned::get_by_stage(VersionedOwnershipTest\Subclass::class, Versioned::DRAFT)
            ->byID($parentID);
        $this->assertListEquals($liveBanners, $parentDraft->Banners());
        $parentLive = Versioned::get_by_stage(VersionedOwnershipTest\Subclass::class, Versioned::LIVE)
            ->byID($parentID);
        $this->assertListEquals($liveBanners, $parentLive->Banners());

        // Check that the newly created banner, even though it still exist, has been
        // unlinked from the reverted draft record
        /** @var VersionedOwnershipTest\RelatedMany $banner4Draft */
        $banner4Draft = Versioned::get_by_stage(VersionedOwnershipTest\RelatedMany::class, Versioned::DRAFT)
            ->byID($banner4->ID);
        $this->assertTrue($banner4Draft->isOnDraft());
        $this->assertFalse($banner4Draft->isPublished());
        $this->assertEmpty($banner4Draft->PageID);
    }

    /**
     * Test that rolling back to a single version works recursively
     */
    public function testRollbackRecursive()
    {
        // Get all objects to be modified in initial state
        $this->sleep(1);

        /** @var VersionedOwnershipTest\Subclass $subclass2 */
        $subclass2 = $this->objFromFixture(VersionedOwnershipTest\Subclass::class, 'subclass2_published');
        // Subclass has_one Related
        /** @var VersionedOwnershipTest\Related $related2 */
        $related2 = $this->objFromFixture(VersionedOwnershipTest\Related::class, 'related2_published');
        // Related many_many Attachment
        // Note: many_many ITSELF is not versioned, so only testing modification to attachment record itself
        /** @var VersionedOwnershipTest\Attachment $attachment3 */
        $attachment3 = $this->objFromFixture(VersionedOwnershipTest\Attachment::class, 'attachment3_published');
        // Subclass has_many RelatedMany
        /** @var VersionedOwnershipTest\RelatedMany $relatedMany4 */
        $relatedMany4 = $this->objFromFixture(VersionedOwnershipTest\RelatedMany::class, 'relatedmany4_published');
        $relatedMany4ID = $relatedMany4->ID;
        $subclass2VersionFirst = $subclass2->Version;

        // Make first set of modifications
        $this->sleep(1);

        // add another related many
        $relatedManyNew = new VersionedOwnershipTest\RelatedMany();
        $relatedManyNew->Title = 'new related many';
        $relatedManyNew->PageID = $subclass2->ID;
        $relatedManyNew->write();

        // Modify existing related many
        $relatedMany4->Title = 'Related Many 4b';
        $relatedMany4->write();

        // Modify has_one related object
        $related2->Title = 'Related 2b';
        $related2->write();

        // modify grandchild object
        $attachment3->Title = 'Attachment 3b';
        $attachment3->write();

        // modify root object
        $subclass2->Title = 'Subclass 2b';
        $subclass2->write();
        $subclass2VersionSecond = $subclass2->Version;

        // make a modification to the root and grand node, but not intermediary
        // this ensures that "gaps" in the hierarchy don't prohibit deeply nested rollbacks
        $this->sleep(1);

        $attachment3->Title = 'Attachment 3c';
        $attachment3->write();

        // Note: Don't write middle object here

        $subclass2->Title = 'Subclass 2c';
        $subclass2->write();
        $subclass2VersionThird = $subclass2->Version;

        // Make modifications involving removal of objects
        $this->sleep(1);
        $relatedMany4->delete();
        $related2->delete();
        $subclass2->RelatedID = null;

        // Modify related many
        $relatedManyNew->Title = 'new related many d';
        $relatedManyNew->write();

        // Modify attachment. Note on special case below: due to $related2 being deleted, this is now
        // an orphaned and not owned by the parent object
        $attachment3->Title = 'Attachment 3d';
        $attachment3->write();

        // Modify root object
        $subclass2->Title = 'Subclass 2d';
        $subclass2->write();
        $subclass2VersionFourth = $subclass2->Version;

        $this->sleep(1);

        // Read historic Banners() for this record prior to rollback.
        // This should be the final "rolled back" list.
        $this->assertListEquals([
            ['Title' => 'Related Many 4b'],
            ['Title' => 'new related many'],
        ], $subclass2->getAtVersion($subclass2VersionSecond)->Banners());

        // Rollback from version C to B
        $subclass2 = $subclass2->rollbackRecursive($subclass2VersionSecond);

        // Check version restored at root
        $this->assertEquals('Subclass 2b', $subclass2->Title);
        // Assert version incremented for version
        $this->assertGreaterThan($subclass2VersionFourth, $subclass2->Version);
        // Deleted Related was restored
        $this->assertEquals('Related 2b', $subclass2->Related()->Title);
        // Deleted RelatedMany was restored
        $this->assertListEquals([
            ['Title' => 'Related Many 4b'],
            ['Title' => 'new related many'],
        ], $subclass2->Banners());
        // Attachment was restored
        $this->assertEquals('Attachment 3b', $attachment3->getAtVersion(Versioned::DRAFT)->Title);

        $this->sleep(1);

        // Read historic Banners() for this record prior to rollback.
        // This should be the final "rolled back" list.
        $this->assertListEquals([
            ['Title' => 'new related many d'],
        ], $subclass2->getAtVersion($subclass2VersionFourth)->Banners());

        // Test rollback can go forwards also from B to C
        $subclass2 = $subclass2->rollbackRecursive($subclass2VersionFourth);

        // Related object is removed again
        $this->assertEmpty($subclass2->RelatedID);

        // Check version restored at root
        $this->assertEquals('Subclass 2d', $subclass2->Title);

        // Deleted RelatedMany was restored but deleted again (bye!)
        $this->assertListEquals([
            ['Title' => 'new related many d'],
        ], $subclass2->Banners());

        // RelatedMany4 still exists, but is "unlinked" thanks to unlinkDisownedObjects()
        $relatedMany4 = VersionedOwnershipTest\RelatedMany::get()->byID($relatedMany4ID);
        $this->assertNotEmpty($relatedMany4);
        $this->assertEquals(0, $relatedMany4->PageID);

        // Test for special edge case: Attachment is no longer attached to the parent record
        // because the intermediary `Related` object was deleted. Thus this is NOT restored,
        // and stays at version B
        $this->assertEquals('Attachment 3b', $attachment3->getAtVersion(Versioned::DRAFT)->Title);

        // Finally, test that rolling back to a version with a gap in it safely rolls back nested records
        $subclass2->rollbackRecursive($subclass2VersionThird);
        $this->assertEquals('Attachment 3c', $attachment3->getAtVersion(Versioned::DRAFT)->Title);
    }

    /**
     * Test that you can find owners without owned_by being defined explicitly
     */
    public function testInferedOwners()
    {
        // Make sure findOwned() works
        /** @var VersionedOwnershipTest\TestPage $page1 */
        $page1 = $this->objFromFixture(VersionedOwnershipTest\TestPage::class, 'page1_published');
        /** @var VersionedOwnershipTest\TestPage $page2 */
        $page2 = $this->objFromFixture(VersionedOwnershipTest\TestPage::class, 'page2_published');
        $this->assertListEquals(
            [
                ['Title' => 'Banner 1'],
                ['Title' => 'Image 1'],
                ['Title' => 'Custom 1'],
            ],
            $page1->findOwned()
        );
        $this->assertListEquals(
            [
                ['Title' => 'Banner 2'],
                ['Title' => 'Banner 3'],
                ['Title' => 'Image 1'],
                ['Title' => 'Image 2'],
                ['Title' => 'Custom 2'],
            ],
            $page2->findOwned()
        );

        // Check that findOwners works
        /** @var VersionedOwnershipTest\Image $image1 */
        $image1 = $this->objFromFixture(VersionedOwnershipTest\Image::class, 'image1_published');
        /** @var VersionedOwnershipTest\Image $image2 */
        $image2 = $this->objFromFixture(VersionedOwnershipTest\Image::class, 'image2_published');

        $this->assertListEquals(
            [
                ['Title' => 'Banner 1'],
                ['Title' => 'Banner 2'],
                ['Title' => 'Page 1'],
                ['Title' => 'Page 2'],
            ],
            $image1->findOwners()
        );
        $this->assertListEquals(
            [
                ['Title' => 'Banner 1'],
                ['Title' => 'Banner 2'],
            ],
            $image1->findOwners(false)
        );
        $this->assertListEquals(
            [
                ['Title' => 'Banner 3'],
                ['Title' => 'Page 2'],
            ],
            $image2->findOwners()
        );
        $this->assertListEquals(
            [
                ['Title' => 'Banner 3'],
            ],
            $image2->findOwners(false)
        );

        // Test custom relation can findOwners()
        /** @var VersionedOwnershipTest\CustomRelation $custom1 */
        $custom1 = $this->objFromFixture(VersionedOwnershipTest\CustomRelation::class, 'custom1_published');
        $this->assertListEquals(
            [['Title' => 'Page 1']],
            $custom1->findOwners()
        );
    }
}
