<?php

namespace SilverStripe\Versioned\Tests\VersionedOwnershipTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class Attachment extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionedOwnershipTest_Attachment';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $belongs_many_many = [
        'AttachedTo' => Related::class . '.Attachments',
    ];

    private static $owned_by = [
        'AttachedTo'
    ];
}
