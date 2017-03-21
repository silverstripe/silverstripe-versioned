<?php

namespace SilverStripe\Versioned\Tests\DataDifferencerTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class HasOneRelationObject extends DataObject implements TestOnly
{
    private static $table_name = 'DataDifferencerTest_HasOneRelationObject';

    private static $db = [
        'Title' => 'Varchar'
    ];

    private static $has_many = [
        'Objects' => TestObject::class
    ];
}
