<?php

namespace SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $Title
 * @mixin RecursivePublishable
 * @mixin Versioned
 * @method HasManyList|GalleryBlock[] GalleryBlocks()
 */
class GalleryBlockPage extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionsDeletedVersionsTest_GalleryBlockPage';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $has_many = [
        'GalleryBlocks' => GalleryBlock::class,
    ];

    private static $owns = [
        'GalleryBlocks',
    ];
}
