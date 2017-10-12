<?php

namespace SilverStripe\Versioned\Tests\VersionedCascadeDeletesTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Versioned\CascadeDeletesExtension;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin CascadeDeletesExtension
 * @mixin Versioned
 * @method ParentObject Parent()
 * @method RelatedObject Related()
 * @method ManyManyList Children()
 */
class ChildObject extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionedCascadeDeletesTest_ChildObject';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $cascade_deletes = [
        'Children',
    ];

    private static $owns = [
        'Children',
        'Related',
    ];

    private static $has_one = [
        'Parent' => ParentObject::class,
        'Related' => RelatedObject::class,
    ];

    private static $many_many = [
        'Children' => GrandChildObject::class,
    ];
}
