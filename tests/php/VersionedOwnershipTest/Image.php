<?php

namespace SilverStripe\Versioned\Tests\VersionedOwnershipTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Simple versioned dataobject
 *
 * @mixin Versioned
 */
class Image extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionedOwnershipTest_Image';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];
}
