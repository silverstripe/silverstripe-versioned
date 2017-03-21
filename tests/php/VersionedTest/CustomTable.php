<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class CustomTable extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar'
    ];

    private static $table_name = 'VTCustomTable';

    private static $extensions = [
        Versioned::class,
    ];
}
