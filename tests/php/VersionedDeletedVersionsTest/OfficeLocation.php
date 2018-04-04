<?php

namespace SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 *
 * @mixin RecursivePublishable
 * @mixin Versioned
 */
class OfficeLocation extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionsDeletedVersionsTest_OfficeLocation';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];
}
