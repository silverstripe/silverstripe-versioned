<?php

namespace SilverStripe\Versioned\Tests\PublishRecursive;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\Queries\SQLSelect;
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
        SlowDummyPage::class,
        SlowDummyObject::class,
    ];

    /**
     * @var array
     */
    protected static $required_extensions = [
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
        $object = SlowDummyObject::create();
        $object->Title = 'Slow object';
        $object->write();

        $page = SlowDummyPage::create();
        $page->Title = 'Slow Page';
        $page->URLSegment = 'slow-page';
        $page->NestedObjectID = (int) $object->ID;
        $page->write();

        $page->publishRecursive();

        $versionsSufix = '_Versions';
        $pageTable = SiteTree::config()->get('table_name');
        $pageVersionedTable = $pageTable . $versionsSufix;
        $objectTable = SlowDummyObject::config()->get('table_name');
        $objectVersionedTable = $objectTable . $versionsSufix;
        $tables = [
            $pageVersionedTable => $page->ID,
            $objectVersionedTable => $object->ID,
        ];

        $results = [];

        foreach ($tables as $table => $id) {
            $query = SQLSelect::create(
                'LastEdited',
                $table,
                ['RecordID' => $id],
                ['Version' => 'DESC'],
                [],
                [],
                1
            );

            $results[] = $query->execute()->value();
        }

        $pageEdit = array_shift($results);
        $objectEdit = array_shift($results);

        $this->assertNotEmpty($pageEdit);
        $this->assertNotEmpty($objectEdit);
        $this->assertEquals($pageEdit, $objectEdit);
    }
}
