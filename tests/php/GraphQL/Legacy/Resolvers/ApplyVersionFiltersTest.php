<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Legacy\Resolvers;

use InvalidArgumentException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Resolvers\ApplyVersionFilters;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Versioned;

class ApplyVersionFiltersTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        Fake::class,
    ];

    protected function setUp()
    {
        parent::setUp();
        if (class_exists(Schema::class)) {
            $this->markTestSkipped('Skipped GraphQL 3 test ' . __CLASS__);
        }
    }

    public function testItValidatesArchiveDate()
    {
        $filter = new ApplyVersionFilters();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/ArchiveDate parameter/');
        $filter->validateArgs(['Mode' => 'archive']);
    }

    public function testItValidatesArchiveDateFormat()
    {
        $filter = new ApplyVersionFilters();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Invalid date/');
        $filter->validateArgs(['Mode' => 'archive', 'ArchiveDate' => '01/12/2018']);
    }

    public function testItValidatesStatusParameter()
    {
        $filter = new ApplyVersionFilters();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Status parameter/');
        $filter->validateArgs(['Mode' => 'status']);
    }

    public function testItValidatesVersionParameter()
    {
        $filter = new ApplyVersionFilters();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Version parameter/');
        $filter->validateArgs(['Mode' => 'version']);
    }

    public function testItSetsReadingStateByMode()
    {
        Versioned::withVersionedMode(function () {
            $filter = new ApplyVersionFilters();
            $filter->applyToReadingState(['Mode' => Versioned::DRAFT]);
            $this->assertEquals(Versioned::DRAFT, Versioned::get_stage());
        });
    }

    public function testItSetsReadingStateByArchiveDate()
    {
        Versioned::withVersionedMode(function () {
            $filter = new ApplyVersionFilters();
            $filter->applyToReadingState(['Mode' => 'archive', 'ArchiveDate' => '2018-01-01']);
            $this->assertEquals('2018-01-01', Versioned::current_archived_date());
        });
    }

    public function testItFiltersByStageOnApplyToList()
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

    public function testItThrowsIfArchiveAndNoDateOnApplyToList()
    {
        $filter = new ApplyVersionFilters();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/ArchiveDate parameter/');
        $list = Fake::get();
        $filter->applyToList($list, ['Mode' => 'archive']);
    }

    public function testItThrowsIfArchiveAndInvalidDateOnApplyToList()
    {
        $filter = new ApplyVersionFilters();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Invalid date/');
        $list = Fake::get();
        $filter->applyToList($list, ['Mode' => 'archive', 'ArchiveDate' => 'foo']);
    }


    public function testItThrowsIfVersionAndNoVersionOnApplyToList()
    {
        $filter = new ApplyVersionFilters();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Version parameter/');
        $list = Fake::get();
        $filter->applyToList($list, ['Mode' => 'version']);
    }

    public function testItSetsArchiveQueryParamsOnApplyToList()
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

    public function testItSetsVersionQueryParamsOnApplyToList()
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

    public function testItSetsLatestVersionQueryParamsOnApplyToList()
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

    public function testItSetsAllVersionsQueryParamsOnApplyToList()
    {
        $filter = new ApplyVersionFilters();
        $list = Fake::get();
        $filter->applyToList(
            $list,
            [
                'Mode' => 'all_versions',
            ]
        );

        $this->assertEquals('all_versions', $list->dataQuery()->getQueryParam('Versioned.mode'));
    }

    public function testItThrowsOnNoStatusOnApplyToList()
    {
        $filter = new ApplyVersionFilters();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Status parameter/');
        $list = Fake::get();
        $filter->applyToList($list, ['Mode' => 'status']);
    }

    public function testStatusOnApplyToList()
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
