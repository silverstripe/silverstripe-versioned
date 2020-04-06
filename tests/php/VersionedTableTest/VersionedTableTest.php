<?php

namespace SilverStripe\Versioned\Tests\VersionedTableTest;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;

class VersionedTableTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'VersionedTableTest.yml';

    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        House::class,
        HouseVisit::class,
        Visitor::class,
        Roof::class,
        WoodenRoof::class,
    ];

    /**
     * @var array
     */
    protected static $required_extensions = [
        House::class => [
            Versioned::class,
        ],
        HouseVisit::class => [
            Versioned::class,
        ],
        Visitor::class => [
            Versioned::class,
        ],
        Roof::class => [
            Versioned::class,
        ],
    ];

    public function testApplyRelationHasOneVersioned()
    {
        Versioned::withVersionedMode(function () {
            Versioned::set_stage(Versioned::DRAFT);

            /** @var House|Versioned $house1 */
            $house1 = $this->objFromFixture(House::class, 'house-1');

            /** @var WoodenRoof|Versioned $roof1 */
            $roof1 = $this->objFromFixture(WoodenRoof::class, 'roof-1');

            $house1->publishRecursive();

            Versioned::withVersionedMode(function () {
                Versioned::set_stage(Versioned::LIVE);

                // check has one
                $columnName = null;
                $roofs = House::get()
                    ->applyRelation('Roof.ID', $columnName)
                    ->where(sprintf('%s IS NOT NULL', $columnName))
                    ->columnUnique($columnName);

                $this->assertEquals(0, count($roofs));
            });

            $roof1->publishRecursive();

            Versioned::withVersionedMode(function () {
                Versioned::set_stage(Versioned::LIVE);

                // check has one
                $columnName = null;
                $roofs = House::get()
                    ->applyRelation('Roof.ID', $columnName)
                    ->where(sprintf('%s IS NOT NULL', $columnName))
                    ->columnUnique($columnName);

                $this->assertEquals(1, count($roofs));
            });
        });
    }

    public function testApplyRelationHasManyAndManyManyVersioned()
    {
        Versioned::withVersionedMode(function () {
            Versioned::set_stage(Versioned::DRAFT);

            /** @var House|Versioned $house1 */
            $house1 = $this->objFromFixture(House::class, 'house-1');

            /** @var HouseVisit|Versioned $houseVisit1 */
            $houseVisit1 = $this->objFromFixture(HouseVisit::class, 'visit-1');

            /** @var Visitor|Versioned $visitor1 */
            $visitor1 = $this->objFromFixture(Visitor::class, 'visitor-1');

            $house1->publishRecursive();

            Versioned::withVersionedMode(function () {
                Versioned::set_stage(Versioned::LIVE);

                // check has many
                $columnName = null;
                $visitors = House::get()
                    ->applyRelation('HouseVisits.VisitorID', $columnName)
                    ->where(sprintf('%s IS NOT NULL', $columnName))
                    ->columnUnique($columnName);

                $this->assertEquals(0, count($visitors));

                // check many many
                $columnName = null;
                $visitors = House::get()
                    ->applyRelation('Visitors.ID', $columnName)
                    ->where(sprintf('%s IS NOT NULL', $columnName))
                    ->columnUnique($columnName);

                $this->assertEquals(0, count($visitors));
            });

            $houseVisit1->publishRecursive();

            Versioned::withVersionedMode(function () {
                Versioned::set_stage(Versioned::LIVE);

                // check has many
                $columnName = null;
                $visitors = House::get()
                    ->applyRelation('HouseVisits.VisitorID', $columnName)
                    ->where(sprintf('%s IS NOT NULL', $columnName))
                    ->columnUnique($columnName);

                $this->assertEquals(1, count($visitors));

                // check many many
                $columnName = null;
                $visitors = House::get()
                    ->applyRelation('Visitors.ID', $columnName)
                    ->where(sprintf('%s IS NOT NULL', $columnName))
                    ->columnUnique($columnName);

                $this->assertEquals(0, count($visitors));
            });

            $visitor1->publishRecursive();

            Versioned::withVersionedMode(function () {
                Versioned::set_stage(Versioned::LIVE);

                // check has many
                $columnName = null;
                $visitors = House::get()
                    ->applyRelation('HouseVisits.VisitorID', $columnName)
                    ->where(sprintf('%s IS NOT NULL', $columnName))
                    ->columnUnique($columnName);

                $this->assertEquals(1, count($visitors));

                // check many many
                $columnName = null;
                $visitors = House::get()
                    ->applyRelation('Visitors.ID', $columnName)
                    ->where(sprintf('%s IS NOT NULL', $columnName))
                    ->columnUnique($columnName);

                $this->assertEquals(1, count($visitors));
            });
        });
    }
}
