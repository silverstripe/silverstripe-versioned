<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;

/**
 * @property string $ExtraField
 */
class Subclass extends TestObject implements TestOnly
{
    private static $table_name = 'VersionedTest_Subclass';

    private static $db = [
        "ExtraField" => "Varchar",
    ];
}
