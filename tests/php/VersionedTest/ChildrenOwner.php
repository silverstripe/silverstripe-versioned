<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $Name
 * @property string $Title
 * @property string $Content
 * @method TestObject Parent()
 * @method HasManyList Children()
 * @method ManyManyList Related()
 * @mixin Versioned
 * @mixin RecursivePublishable
 */
class ChildrenOwner extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedTest_ChildrenOwner';

    private static $db = [
        "Name" => "Varchar",
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $has_one = [
        'Parent' => ChildrenOwner::class,
    ];

    private static $has_many = [
        'Children' => ChildrenOwner::class,
    ];

    private static $owns = [
        'Children',
    ];

}
