<?php

namespace SilverStripe\Versioned\Tests\VersionedManyManyThroughListTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyThroughList;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * Basic parent object
 *
 * @property string $Title
 * @method ManyManyThroughList Items()
 * @mixin Versioned
 * @mixin RecursivePublishable
 */
class VersionedObject extends DataObject implements TestOnly
{
    private static $table_name = 'ManyManyThroughListTest_VersionedObject';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $owns = [
        'Items', // Should automatically own both mapping and child records
    ];

    private static $many_many = [
        'Items' => [
            'through' => VersionedJoinObject::class,
            'from' => 'Parent',
            'to' => 'Child',
        ],
    ];
}
