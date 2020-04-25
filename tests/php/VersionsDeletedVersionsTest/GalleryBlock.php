<?php

namespace SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 *
 * @mixin RecursivePublishable
 * @mixin Versioned
 * @method HasManyList|GalleryBlockItem[] Items()
 */
class GalleryBlock extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionsDeletedVersionsTest_GalleryBlock';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $has_one = [
        'GalleryBlockPage' => GalleryBlockPage::class,
    ];

    private static $has_many = [
        'Items' => GalleryBlockItem::class,
    ];

    private static $owns = [
        'Items',
    ];
}
