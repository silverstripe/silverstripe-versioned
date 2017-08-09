<?php

namespace SilverStripe\Versioned\Tests\VersionedCascadeDeletesTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\CascadeDeletesExtension;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin CascadeDeletesExtension
 * @mixin Versioned
 */
class GrandChildObject extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionedCascadeDeletesTest_GrandChildObject';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $belongs_many_many = [
        'Parents' => ChildObject::class,
    ];
}
