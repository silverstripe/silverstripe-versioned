<?php

namespace SilverStripe\Versioned\Tests\VersionedLazyLoadingTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class VersionedObject extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedLazy_DataObject';

    private static $db = [
        "PageName" => "Varchar"
    ];

    private static $extensions = [
        Versioned::class
    ];
}
