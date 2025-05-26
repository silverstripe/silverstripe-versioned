<?php

namespace SilverStripe\Versioned\Tests\Traits;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Dev\TestOnly;

class TestObject extends DataObject implements TestOnly
{
    private static $table_name = 'Versioned_Tests_Traits_TestObject';

    private static array $db = [
        'Title' => 'Varchar'
    ];

    private static array $extensions = [
        Versioned::class,
    ];
}
