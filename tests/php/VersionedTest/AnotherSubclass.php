<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;

/**
 * @property string $AnotherField
 */
class AnotherSubclass extends TestObject implements TestOnly
{
    private static $table_name = 'VersionedTest_AnotherSubclass';

    private static $db = [
        "AnotherField" => "Varchar"
    ];
}
