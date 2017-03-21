<?php

namespace SilverStripe\Versioned\Tests\VersionedLazyLoadingTest;

use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class VersionedSubObject extends VersionedObject
{
    private static $table_name = 'VersionedLazySub_DataObject';

    private static $db = [
        "ExtraField" => "Varchar",
    ];
    private static $extensions = [
        Versioned::class
    ];
}
