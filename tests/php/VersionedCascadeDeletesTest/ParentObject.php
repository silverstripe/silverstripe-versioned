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
class ParentObject extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionedCascadeDeletesTest_ParentObject';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $cascade_deletes = [
        'Children',
    ];

    private static $owns = [
        'Children',
    ];

    private static $has_many = [
        'Children' => ChildObject::class,
    ];
}
