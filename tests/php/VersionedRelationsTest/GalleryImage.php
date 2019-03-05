<?php


namespace SilverStripe\Versioned\Tests\VersionedRelationsTest;


use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class GalleryImage extends DataObject implements TestOnly
{
    private static $db = [
        'URL' => 'Varchar',
    ];

    private static $belongs_many_many = [
        'Gallery' => Gallery::class,
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionedRelationsTest_GalleryImage';


}
