<?php

namespace App\Tests\VersionsCleanup;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Ship extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'VersionsCleanup_Ship';

    /**
     * @var string[]
     */
    private static $db = [
        'Title' => 'Varchar',
    ];
}
