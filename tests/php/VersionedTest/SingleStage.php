<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class SingleStage extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedTest_SingleStage';

    private static $db = [
        'Name' => 'Varchar'
    ];

    private static $extensions = [
        Versioned::class . '.versioned',
    ];
}
