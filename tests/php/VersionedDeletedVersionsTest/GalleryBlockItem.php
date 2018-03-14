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
class GalleryBlockItem extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionsDeletedVersionsTest_GalleryBlockItem';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $default_sort = [
        '"VersionsDeletedVersionsTest_GalleryBlockItem"."Title" ASC',
    ];

    private static $has_one = [
        'GalleryBlock' => GalleryBlock::class,
    ];
}
