<?php

namespace SilverStripe\Versioned\Tests\VersionedOwnershipTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\HasManyList;

/**
 * Object which:
 * - owns a has_one object
 * - owns has_many objects
 *
 * @property string $Description
 * @property int $RelatedID
 * @method Related Related()
 * @method HasManyList|RelatedMany[] Banners()
 */
class Subclass extends TestObject implements TestOnly
{
    private static $db = [
        'Description' => 'Text',
    ];

    private static $has_one = [
        'Related' => Related::class,
    ];

    private static $has_many = [
        'Banners' => RelatedMany::class,
    ];

    private static $table_name = 'VersionedOwnershipTest_Subclass';

    private static $owns = [
        'Related',
        'Banners',
    ];
}
