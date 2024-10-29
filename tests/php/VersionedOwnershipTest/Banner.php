<?php

namespace SilverStripe\Versioned\Tests\VersionedOwnershipTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Staged\RecursivePublishable;
use SilverStripe\Versioned\Mode\Versioned;

/**
 * Banner which doesn't declare its belongs_many_many, but owns an Image
 *
 * @mixin RecursivePublishable
 * @mixin Versioned
 */
class Banner extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionedOwnershipTest_Banner';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $has_one = [
        'Image' => Image::class,
    ];

    private static $owns = [
        'Image',
    ];
}
