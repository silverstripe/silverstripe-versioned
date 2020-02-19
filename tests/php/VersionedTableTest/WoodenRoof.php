<?php

namespace SilverStripe\Versioned\Tests\VersionedTableTest;

/**
 * Class WoodenRoof
 *
 * @property int $WoodType
 * @method House House()
 * @package SilverStripe\Versioned\Tests\VersionedTableTest
 */
class WoodenRoof extends Roof
{
    /**
     * @var string
     */
    private static $table_name = 'VersionedTableTest_WoodenRoof';

    /**
     * @var array
     */
    private static $db = [
        'WoodType' => 'Int',
    ];

    /**
     * @var array
     */
    private static $belongs_to = [
        'House' => House::class.'.WoodenRoof',
    ];
}
