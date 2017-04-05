<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Versioned\Versioned;
use SilverStripe\ORM\Hierarchy\Hierarchy;

/**
 * @property string $Name
 * @property string $Title
 * @property string $Content
 * @method ChangeSetTestObject Parent()
 * @method HasManyList Children()
 * @method ManyManyList Related()
 * @mixin Versioned
 */
class ChangeSetTestObject extends DataObject implements TestOnly
{
    private static $table_name = 'ChangeSetVersionedTest_DataObject';

    private static $db = [
        "Name" => "Varchar",
        'Title' => 'Varchar',
    ];

    private static $extensions = [
        Versioned::class,
        Hierarchy::class
    ];

    private static $has_one = [
        'Parent' => ChangeSetTestObject::class,
    ];

    private static $has_many = [
        'Children' => ChangeSetTestObject::class,
    ];

    private static $many_many = [
        'Related' => RelatedWithoutversion::class,
    ];
}
