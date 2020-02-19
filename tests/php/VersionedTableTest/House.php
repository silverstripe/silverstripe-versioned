<?php

namespace SilverStripe\Versioned\Tests\VersionedTableTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyThroughList;
use SilverStripe\Versioned\Versioned;

/**
 * Class House
 *
 * @property string $Title
 * @property int $RoofID
 * @method WoodenRoof Roof()
 * @method ManyManyThroughList|Visitor[] Visitors()
 * @method HasManyList|HouseVisit[] HouseVisits()
 * @package SilverStripe\Versioned\Tests\VersionedTableTest
 */
class House extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'VersionedTableTest_House';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(50)',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Roof' => WoodenRoof::class,
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'HouseVisits' => HouseVisit::class . '.House',
    ];

    /**
     * @var array
     */
    private static $many_many = [
        'Visitors' => [
            'through' => HouseVisit::class,
            'from' => 'House',
            'to' => 'Visitor',
        ],
    ];

    /**
     * @var array
     */
    private static $extensions = [
        Versioned::class,
    ];
}
