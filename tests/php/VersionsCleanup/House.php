<?php

namespace App\Tests\VersionsCleanup;

use Page;
use SilverStripe\Dev\TestOnly;

class House extends Page implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'VersionsCleanup_House';

    /**
     * @var string[]
     */
    private static $db = [
        'Address' => 'Varchar',
    ];
}
