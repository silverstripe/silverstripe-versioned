<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Resolvers;

use InvalidArgumentException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Resolvers\ApplyVersionFilters;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Versioned;

class ApplyVersionFiltersTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        Fake::class,
    ];

    public function testItFiltersByStage()
    {
        $filter = new ApplyVersionFilters();
        $record1 = new Fake();
        $record1->Name = 'First version draft';
        $record1->write();

        $record2 = new Fake();
        $record2->Name = 'First version live';
        $record2->write();
        $record2->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);

        $list = Fake::get();
        $filter->applyToList($list, ['Mode' => Versioned::DRAFT]);
        $this->assertCount(2, $list);

        $list = Fake::get();
        $filter->applyToList($list, ['Mode' => Versioned::LIVE]);
        $this->assertCount(1, $list);
    }

    public function testItThrowsIfArchiveAndNoDate()
    {
        $filter = new ApplyVersionFilters();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/ArchiveDate parameter/');
        $filter->applyToList($list, ['Mode' => 'archive']);
    }

    public function testItThrowsIfArchiveAndInvalidDate()
    {
        $filter = new ApplyVersionFilters();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Invalid date/');
        $filter->applyToList($list, ['Mode' => 'archive', 'ArchiveDate' => 'foo']);
    }


    public function testItThrowsIfVersionAndNoVersion()
    {
        $filter = new ApplyVersionFilters();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Version parameter/');
        $filter->applyToList($list, ['Mode' => 'version']);
    }

    public function testItSetsArchiveQueryParams()
    {
        $filter = new ApplyVersionFilters();
        $list = Fake::get();
        $filter->applyToList(
            $list,
            [
                'Mode' => 'archive',
                'ArchiveDate' => '2016-11-08',
            ]
        );

        $this->assertEquals('archive', $list->dataQuery()->getQueryParam('Versioned.mode'));
        $this->assertEquals('2016-11-08', $list->dataQuery()->getQueryParam('Versioned.date'));
    }

    public function testItSetsVersionQueryParams()
    {
        $filter = new ApplyVersionFilters();
        $list = Fake::get();
        $filter->applyToList(
            $list,
            [
                'Mode' => 'version',
                'Version' => '5',
            ]
        );

        $this->assertEquals('version', $list->dataQuery()->getQueryParam('Versioned.mode'));
        $this->assertEquals('5', $list->dataQuery()->getQueryParam('Versioned.version'));
    }

    public function testItSetsLatestVersionQueryParams()
    {
        $filter = new ApplyVersionFilters();
        $list = Fake::get();
        $filter->applyToList(
            $list,
            [
                'Mode' => 'latest_versions',
            ]
        );

        $this->assertEquals('latest_versions', $list->dataQuery()->getQueryParam('Versioned.mode'));
    }

    public function testItThrowsOnNoStatus()
    {
        $filter = new ApplyVersionFilters();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Status parameter/');
        $list = Fake::get();
        $filter->applyToList($list, ['Mode' => 'status']);
    }

    public function testStatus()
    {
        $filter = new ApplyVersionFilters();
        $record1 = new Fake();
        $record1->Name = 'Only on draft';
        $record1->write();

        $record2 = new Fake();
        $record2->Name = 'Published';
        $record2->write();
        $record2->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);

        $record3 = new Fake();
        $record2->Name = 'Will be modified';
        $record3->write();
        $record3->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        $record3->Name = 'Modified';
        $record3->write();

        $record4 = new Fake();
        $record4->Name = 'Will be archived';
        $record4->write();
        $record4->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        $oldID = $record4->ID;
        $record4->delete();
        $list = Fake::get();
        $filter->applyToList(
            $list,
            [
                'Mode' => 'status',
                'Status' => ['modified']
            ]
        );
        $this->assertListEquals([['ID' => $record3->ID]], $list);

        $list = Fake::get();
        $filter->applyToList(
            $list,
            [
                'Mode' => 'status',
                'Status' => ['archived']
            ]
        );
        $this->assertCount(1, $list);
        $this->assertEquals($oldID, $list->first()->ID);

        $list = Fake::get();
        $filter->applyToList(
            $list,
            [
                'Mode' => 'status',
                'Status' => ['draft']
            ]
        );

        $this->assertCount(1, $list);
        $ids = $list->column('ID');
        $this->assertContains($record1->ID, $ids);


        $list = Fake::get();
        $filter->applyToList(
            $list,
            [
                'Mode' => 'status',
                'Status' => ['draft', 'modified']
            ]
        );

        $this->assertCount(2, $list);
        $ids = $list->column('ID');

        $this->assertContains($record3->ID, $ids);
        $this->assertContains($record1->ID, $ids);

        $list = Fake::get();
        $filter->applyToList(
            $list,
            [
                'Mode' => 'status',
                'Status' => ['archived', 'modified']
            ]
        );

        $this->assertCount(2, $list);
        $ids = $list->column('ID');
        $this->assertTrue(in_array($record3->ID, $ids));
        $this->assertTrue(in_array($oldID, $ids));
    }
}
