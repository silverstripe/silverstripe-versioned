<?php


namespace SilverStripe\Versioned\Tests;


use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\SS_List;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Snapshot;
use SilverStripe\Versioned\SnapshotItem;
use SilverStripe\Versioned\Tests\VersionedRelationsTest\Block;
use SilverStripe\Versioned\Tests\VersionedRelationsTest\BlockPage;
use SilverStripe\Versioned\Tests\VersionedRelationsTest\Gallery;
use SilverStripe\Versioned\Tests\VersionedRelationsTest\GalleryImage;
use SilverStripe\Versioned\Tests\VersionedRelationsTest\GalleryImageJoin;
use SilverStripe\Versioned\Versioned;

class VersionedRelationsTest extends FunctionalTest
{

    protected $usesDatabase = true;

    protected $usesTransactions = false;

    protected static $extra_dataobjects = [
        BlockPage::class,
        Block::class,
        Gallery::class,
        GalleryImage::class,
        GalleryImageJoin::class,
    ];

    public function testHistoryIncludesOwnedObjects()
    {
        // Model:
        // BlockPage
        //  -> (has_many/owns) -> Blocks
        //      -> (has_many/owns) -> Gallery
        //          -> (many_many/owns) -> GalleryImage


        $a1 = new BlockPage(['Title' => 'A1 Block Page']);
        $a1->write();
        $a1->publishRecursive();

        $a2 = new BlockPage(['Title' => 'A2 Block Page']);
        $a2->write();
        $a2->publishRecursive();

        // Starting point. An entry for draft and publish, plus a snapshot (empty)
        $this->assertCount(3, $this->getHistory($a1));

        $a1Block1 = new Block(['Title' => 'Block 1 on A1', 'ParentID' => $a1->ID]);
        $a1Block1->write();
        $a1Block2 = new Block(['Title' => 'Block 2 on A1', 'ParentID' => $a1->ID]);
        $a1Block2->write();

        // A1
        //   block1 (draft, new) *
        //   block2 (draft, new) *

        // A new entry for each block added.
        $this->assertCount(5, $this->getHistory($a1));

        $a2Block1 = new Block(['Title' => 'Block 1 on A2', 'ParentID' => $a2->ID]);
        $a2Block1->write();

        // A1
        //   block1 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new) *

        // A new entry for the one block added to the SIBLING.
        $this->assertCount(4, $this->getHistory($a2));


        $a1->Title = 'A1 Block Page -- changed';
        $a1->write();

        // A1 (draft, modified) *
        //   block1 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A new entry for the modified BlockPage
        $this->assertCount(6, $this->getHistory($a1));

        $a1Block1->Title = 'Block 1 on A1 -- changed';
        $a1Block1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A new entry for the modified Block <- BlockPage
        $this->assertCount(7, $this->getHistory($a1));

        // A1 will publish its two blocks
        $this->assertTrue($this->hasOwnedModifications($a1));

        // Since publishing:
        //  two new blocks created
        //  one of those was then modified.
        $activity = $this->getActivitySinceLastPublish($a1);
        $this->assertCount(3, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, 'CREATED'],
                [$a1Block1, 'MODIFIED'],
                [$a1Block2, 'CREATED']
            ]
        );

        // Testing third level
        $gallery1 = new Gallery(['Title' => 'Gallery 1 on Block 1 on A1', 'BlockID' => $a1Block1->ID]);
        $gallery1->write();
        // A new entry for the Gallery <- Block <- BlockPage
        $this->assertCount(8, $this->getHistory($a1));

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, new) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A1 will now publish two blocks and a gallery
        $this->assertTrue($this->hasOwnedModifications($a1));

        // Since last publish:
        //  two blocks were created
        //  one block was modified
        //  one gallery created.
        $activity = $this->getActivitySinceLastPublish($a1);
        $this->assertCount(4, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, 'CREATED'],
                [$a1Block1, 'MODIFIED'],
                [$a1Block2, 'CREATED'],
                [$gallery1, 'CREATED'],
            ]
        );

        $gallery1->Title = 'Gallery 1 on Block 1 on A1 -- changed';
        $gallery1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, modified) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        // A new entry for the modified Gallery <- Block <- BlockPage
        $this->assertCount(9, $this->getHistory($a1));

        // A1 will still publish two blocks and a gallery
        $this->assertTrue($this->hasOwnedModifications($a1));

        // Since last publish:
        //  two blocks were created
        //  one block was modified
        //  one gallery created
        //  one gallery was modified
        $activity = $this->getActivitySinceLastPublish($a1);
        $this->assertCount(5, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, 'CREATED'],
                [$a1Block1, 'MODIFIED'],
                [$a1Block2, 'CREATED'],
                [$gallery1, 'CREATED'],
                [$gallery1, 'MODIFIED'],
            ]
        );

        // Testing many_many
        $galleryItem1 = new GalleryImage(['URL' => '/gallery/image/1']);
        $galleryItem2 = new GalleryImage(['URL' => '/gallery/image/2']);

        $gallery1->Images()->add($galleryItem1);
        $gallery1->Images()->add($galleryItem2);

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, new)
        //          image1 (draft, new) *
        //          image2 (draft, new) *
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)

        $activity = $this->getActivitySinceLastPublish($a1);
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, 'CREATED'],
                [$a1Block1, 'MODIFIED'],
                [$a1Block2, 'CREATED'],
                [$gallery1, 'CREATED'],
                [$gallery1, 'MODIFIED'],
                [$galleryItem1, 'ADDED', $gallery1],
                [$galleryItem2, 'ADDED', $gallery1],
            ]
        );

        // Two new entries for the new GalleryItem <- Gallery <- Block <- BlockPage
        $this->assertCount(11, $this->getHistory($a1));

        $gallery1a = new Gallery(['Title' => 'Gallery 1 on Block 1 on A2', 'BlockID' => $a2Block1->ID]);
        $gallery1a->write();
        $gallery1a->Images()->add($galleryItem1);

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, new)
        //          image1 (draft, new)
        //          image2 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)
        //       gallery1a (draft, new) *
        //          image1 (draft, new) *

        // New gallery, new image
        $this->assertCount(6, $this->getHistory($a2));

        $this->assertTrue($this->hasOwnedModifications($a2));

        $activity = $this->getActivitySinceLastPublish($a2);
        $this->assertCount(3, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a2Block1, 'CREATED'],
                [$gallery1a, 'CREATED'],
                [$galleryItem1, 'ADDED', $gallery1a],
            ]
        );

        $galleryItem1->URL = '/changed/url';
        $galleryItem1->write();

        // A1 (draft, modified)
        //   block1 (draft, modified)
        //       gallery1 (draft, new)
        //          image1 (draft, modified) *
        //          image2 (draft, new)
        //   block2 (draft, new)
        // A2
        //   block1 (draft, new)
        //       gallery1a (draft, new)
        //          image1 (draft, modified) *

        $this->assertCount(7, $this->getHistory($a2));

        $this->assertTrue($this->hasOwnedModifications($a2));

        $activity = $this->getActivitySinceLastPublish($a2);
        $this->assertCount(4, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a2Block1, 'CREATED'],
                [$gallery1a, 'CREATED'],
                [$galleryItem1, 'ADDED', $gallery1a],
                [$galleryItem1, 'MODIFIED'],
            ]
        );

        $this->assertCount(12, $this->getHistory($a1));

        $this->assertTrue($this->hasOwnedModifications($a1));

        $activity = $this->getActivitySinceLastPublish($a1);
        $this->assertCount(8, $activity);
        $this->assertActivityContains(
            $activity,
            [
                [$a1Block1, 'CREATED'],
                [$a1Block1, 'MODIFIED'],
                [$a1Block2, 'CREATED'],
                [$gallery1, 'CREATED'],
                [$gallery1, 'MODIFIED'],
                [$galleryItem1, 'ADDED', $gallery1],
                [$galleryItem2, 'ADDED', $gallery1],
                [$galleryItem1, 'MODIFIED'],
            ]
        );
        // Publish, and clear the slate
        $a1->publishRecursive();

        // New new live, new draft versions
        $this->assertCount(14, $this->getHistory($a1));

        $this->assertFalse($this->hasOwnedModifications($a1));
        $this->assertTrue($this->hasOwnedModifications($a2));

        $a2->publishRecursive();

        $this->assertFalse($this->hasOwnedModifications($a1));
        $this->assertFalse($this->hasOwnedModifications($a2));

        $this->assertEmpty($this->getActivitySinceLastPublish($a1));
        $this->assertEmpty($this->getActivitySinceLastPublish($a2));

        $gallery1->Title = 'Gallery 1 is changed again';
        $gallery1->write();
        $this->assertCount(14, $this->getHistory($a1));
        $toPublish = $this->getObjectsToPublish($a1);
        $this->assertCount(2, $toPublish);
        $this->assertSnapshotsContain($toPublish, [$gallery1, $a1Block1]);
        $activity = $this->getActivitySinceLastPublish($a1);
        $this->assertCount(1, $activity);
        $this->assertActivityContains($activity, [
            [$gallery1, 'MODIFIED'],
        ]);
        // Maybe someday: Assert activity between v1 and current is EVERYTHING that's happened in this test thus far

        // ROLLBACK $gallery1
        /// Assert history decremements by 1
            /// OR history incremements by 1
        // Assert unpublished owned is EMPTY
        // assert changes between $a1[version 2] and $a1[current] is EMPTY

        // Intermediate ownership
        // Change gallery1
        // assert BlockPage has unpublished owned
        // assert Block has unpublished owned
        // Does this item belong to a snapshot that has unpublished changes

        // Change A1 BlockPage
        // Publish Block
        // assert Block has unpublished owned EMPTY
        // BlockPage has unpublished owned NOT EMPTY
        // assert gallery1 has unpublished owned EMPTY

        // Make sure siblings weren't affected by all this.
        $this->assertCount(4, $this->getHistory($a2));


        // Publish A1
        // modify block 1
        // assert A1 has unpublished owned $block1
        // assert A1 history increment
        // move block 1 to A2
        // assert A2 has unpublished owned contains $block1
        // assert A1 has unpublished owned EMPTY
        // assert A2 history increment
        // assert A1 history decrement

        // ------------ reset state -----------//
        // change Block1

        $a1Block1->delete();
        // Assert A1 history is UNCHANGED
        // assert a1 unpublished owned is EMPTY

        // assert changes between $a1[version 1] and $a1[current] is
        //  $a1Block1 CREATED
        //  $a1Block1 MODIFIED
                //  $a1Block1 DELETED

        // Add block2
        // publish block 2
        // unpublish block 2
        // assert A1 history is increment by 3
        // assert A1 unpublished owned is $block2

    }

    protected function getRelevantSnapshots(DataObject $obj)
    {
        $class = get_class($obj);
        $id = $obj->ID;
        if (!$class::singleton()->hasExtension(RecursivePublishable::class)) {
            return false;
        }

        $publishedVersion = Versioned::get_versionnumber_by_stage($class, Versioned::LIVE, $id);
        $hash = Snapshot::hash($class, $id);
        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        $result = Snapshot::get()
            ->innerJoin($itemTable, "\"$snapshotTable\".\"ID\" = \"$itemTable\".\"SnapshotID\"")
            ->where([
                // Only snapshots that this record was involved in
                ['ObjectHash = ?' => $hash],
                // After it was published
                ['Version >= ?' => $publishedVersion],
                // But not snapshots that were instantiated by itself.
                // Making a change to an intermediate node should only affect its owners' activity,
                // not its owned nodes.
                ['OriginHash != ?' => $hash],
            ]);
//            ->where([
//                ['ObjectHash = ?' => $hash],
//                ['Version >=' => $publishedVersion],
//            ])

            return $result;
    }
    /**
     * @param DataObject $obj
     * @param bool $flatten
     * @return SS_List
     */
    protected function getActivity(DataObject $obj)
    {
        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);
        $snapShotIDs = $this->getRelevantSnapshots($obj)->column('ID');

        if(!empty($snapShotIDs)) {
            $result = SnapshotItem::get()
                ->innerJoin($snapshotTable, "\"$snapshotTable\".\"ID\" = \"$itemTable\".\"SnapshotID\"")
                ->filter([
                    // Only relevant snapshots
                    'SnapshotID' => $snapShotIDs,
                ])
                ->where(
                    // Only get the items that were the subject of a user's action
                    "\"$snapshotTable\" . \"OriginHash\" = \"$itemTable\".\"ObjectHash\""
                );

            return $result;
        }

        return ArrayList::create();
    }
    /**
     * @param DataObject $obj
     * @return boolean
     */
    protected function hasOwnedModifications(DataObject $obj)
    {
        $hash = Snapshot::hash(get_class($obj), $obj->ID);
        $snapShotIDs = $this->getRelevantSnapshots($obj)->column('ID');
        if (empty($snapShotIDs)) {
            return false;
        }
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);
        $query = new SQLSelect(
            ['MaxID' => "MAX($itemTable.ID)"],
            $itemTable
        );
        $query->setWhere([
            ['SnapshotID IN (' . DB::placeholders($snapShotIDs) . ')' => $snapShotIDs],
            ['WasPublished = ?' => 0],
            ['WasDeleted = ?' => 0],
            ['ObjectHash != ? ' => $hash]
        ])
            ->setGroupBy('ObjectHash')
            ->setOrderBy('Created DESC');

        $result = $query->execute();

        return $result->numRecords() > 0;


//
//
//        $snapshotItemIDs = SnapshotItem::get()
//            ->filter([
//                'SnapshotID' => $snapShotIDs,
//            ])
//            ->alterDataQuery(function(DataQuery $query) use ($itemTable) {
//            });
//        $class = get_class($obj);
//        $id = $obj->ID;
//        if (!$class::singleton()->hasExtension(RecursivePublishable::class)) {
//            return false;
//        }
//        $publishedVersion = Versioned::get_versionnumber_by_stage($class, Versioned::LIVE, $id);
//        $hash = md5($class . $id);
//        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
//        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);
//
//        $idField = $flatten ? "MAX($itemTable.ID)" : "$itemTable.ID";
//        $groupBy = $flatten ? "GROUP BY ObjectHash" : '';
//
//        // Get all snapshots where this version of the owner has been involved
//        $sql1 = <<<SQL
//    SELECT $snapshotTable . ID
//				FROM $snapshotTable
//				LEFT JOIN $itemTable ON $snapshotTable . ID = $itemTable . SnapshotID
//				WHERE
//					ObjectHash = '$hash'
//                    AND Version >= '$publishedVersion'
//				ORDER BY $snapshotTable . Created DESC
//SQL;
//        // Only get latest version entry for each item
//        $sql2 = <<<SQL
//    SELECT $idField FROM $itemTable
//		LEFT JOIN $snapshotTable ON $snapshotTable . ID = $itemTable . SnapshotID
//		WHERE
//			$itemTable . SnapshotID IN($sql1)
//		$groupBy
//		ORDER BY $itemTable . Created DESC
//SQL;
//        // Filter to only draft items
//
//        $sql = <<<SQL
//SELECT * FROM $itemTable
//WHERE
//	ID IN($sql2)
//	AND ObjectHash != '$hash'
//	AND WasDeleted = 0
//AND WasPublished = 0;
//SQL;
//        $result = DB::query($sql);
//
//        // Hack for createDataObject() on a custom SQL query
//        $ids = [];
//        while ($row = $result->nextRecord()) {
//            $ids[] = $row['ID'];
//        }
//        if (!empty($ids)) {
//            return SnapshotItem::get()->byIDs($ids);
//        }
//
//        return null;
    }

    /**
     * @param DataObject $obj
     * @return ArrayList
     */
    protected function getHistory(DataObject $obj)
    {
        $class = get_class($obj);
        $id = $obj->ID;

        $list = ArrayList::create();
        $versions = Versioned::get_all_versions($class, $id);

       foreach ($versions as $version) {
           $list->push($version);
       }
       $snapshots = $this->getSnapshotsSinceLastPublish($obj);
       foreach ($snapshots as $snapshot) {
           $list->push($snapshot);
       }

       return $list;
    }

    /**
     * @param DataObject $obj
     * @return ArrayList|DataList
     */
    protected function getSnapshotsSinceLastPublish(DataObject $obj)
    {
        $class = get_class($obj);
        $id = $obj->ID;
        $latest = Versioned::get_versionnumber_by_stage($class, Versioned::LIVE, $id);
        if (!$latest) {
            return ArrayList::create();
        }
        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);
        return Snapshot::get()
            ->leftJoin($itemTable, "\"$snapshotTable\".\"ID\" = \"$itemTable\".\"SnapshotID\"")
            ->where(
                ['ObjectHash = ?' =>  md5($class . $id)],
                ["\"$itemTable\".\"Version\" > ?" => $latest]
            )
            ->sort('Created DESC');

    }

    /**
     * @param DataObject $obj
     * @return array
     */
    protected function getActivitySinceLastPublish(DataObject $obj)
    {
        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);

        $items = $this->getActivity($obj);
        if (!$items->exists()) return [];
        $activity = [];
        foreach ($items as $item) {
            $flag = null;
            if ($item->LinkedToObject()->exists()) {
                $activity[] = [
                    Snapshot::hash($item->LinkedToObjectClass, $item->LinkedToObjectID),
                    $item->LinkedToObjectClass,
                    $item->LinkedToObjectID,
                    'ADDED',
                    $item->LinkedFromObjectClass,
                    $item->LinkedFromObjectID,
                ];
            } else {
                if ($item->Version == 1) {
                    $flag = 'CREATED';
                } else {
                    if ($item->WasDeleted) {
                        $flag = 'DELETED';
                    } else {
                        $flag = 'MODIFIED';
                    }
                }
                $activity[] = [
                    $item->ObjectHash,
                    $item->ObjectClass,
                    $item->ObjectID,
                    $flag,
                    null,
                    null,
                ];
            }
        }

        return $activity;
    }
//

//    protected function assertSnapshotsContain($snapshots, $objs = [])
//    {
//        $expected = array_map(function ($obj) {
//            return md5(get_class($obj) . $obj->ID);
//        }, $objs);
//        $originalCount = sizeof($expected);
//        foreach ($snapshots as $snapshot) {
//            $searchHash = $snapshot->LinkedToObject()->exists()
//                ? md5($snapshot->LinkedToObjectClass . $snapshot->LinkedToObjectID)
//                : $snapshot->ObjectHash;
//            $match = array_search($searchHash, $expected);
//            if ($match !== false) {
//                unset($expected[$match]);
//            }
//        }
//        $this->assertCount($originalCount, $snapshots);
//        $this->assertEmpty($expected);
//    }

    protected function assertActivityContains($activity, $objs = [])
    {
        $expected = array_map(function ($data) {
            if (!isset($data[2])) {
                $data[2] = null;
            }
            list ($obj, $flag, $owner) = $data;
            $sku = get_class($obj) . '__' . $obj->ID . '__' . $flag;
            if ($owner) {
                $sku .= '__' . get_class($owner) . '__' . $owner->ID;
            }

            return $sku;
        }, $objs);
        $originalCount = sizeof($expected);
        foreach ($activity as $a) {
            list ($hash, $class, $id, $flag, $ownerClass, $ownerID) = $a;
            $search = $class . '__' . $id . '__' . $flag;
            if ($ownerClass && $ownerID) {
                $search .= '__' . $ownerClass . '__' . $ownerID;
            }
            $match = array_search($search, $expected);
            if ($match !== false) {
                unset($expected[$match]);
            }
        }
        $this->assertCount($originalCount, $activity);
        $this->assertEmpty($expected);
    }

}


//    protected function getOwnedAtVersion(DataObject $obj, $version)
//    {
//        $class = get_class($obj);
//        $id = $obj->ID;
//        $list = ArrayList::create();
//        $snapshotTable = DataObject::getSchema()->tableName(Snapshot::class);
//        $itemTable = DataObject::getSchema()->tableName(SnapshotItem::class);
//
//        $lowerSnapshot = Snapshot::get()
//            ->leftJoin($itemTable, "\"$snapshotTable\".\"ID\" = \"$itemTable\".\"SnapshotID\"")
//            ->filter([
//                'ObjectHash' => md5($class . $id),
//                'WasPublished' => true
//            ])
//            ->sort('Created ASC')
//            ->first();
//        if (!$lowerSnapshot) {
//            return $list;
//        }
//        $lowerSnapshotID = $lowerSnapshot->ID;
//
//        $upperSnapshot = Snapshot::get()
//            ->leftJoin($itemTable, "\"$snapshotTable\".\"ID\" = \"$itemTable\".\"SnapshotID\"")
//            ->filter([
//                'ObjectHash' => md5($class . $id),
//                'WasPublished' => true,
//                'Version' => $version,
//            ])
//            ->sort('Created ASC')
//            ->first();
//
//        if (!$upperSnapshot) {
//            return $list;
//        }
//        $upperSnapshotID = $upperSnapshot->ID;
//        $sql = <<<SQL
//SELECT * FROM $itemTable
//WHERE
//	ID IN (
//		SELECT MAX($itemTable.ID) FROM $itemTable
//		LEFT JOIN $snapshotTable ON $snapshotTable.ID = $itemTable.VersionSnapshotID
//		WHERE $itemTable.VersionSnapshotID BETWEEN $lowerSnapshotID AND $upperSnapshotID
//		GROUP BY ObjectHash
//		ORDER BY Created ASC
//	)
//	AND WasDeleted = 0
//SQL;
//        $result = DB::query($sql);
//        while($row = $result->nextRecord()) {
//            $class = $row['ObjectClass'];
//            $id = $row['ObjectID'];
//            $version = $row['Version'];
//            $list->push(Versioned::get_version($class, $id, $version));
//        }
//
//        return $list;
//    }
