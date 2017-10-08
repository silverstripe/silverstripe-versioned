<?php

namespace SilverStripe\Versioned\Tests\VersionedOwnershipTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Object which:
 * - owned by has_many objects
 * - owns many_many Objects
 *
 * @mixin Versioned
 */
class Related extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionedOwnershipTest_Related';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $has_many = [
        'Parents' => Subclass::class . '.Related',
    ];

    private static $owned_by = [
        'Parents',
    ];

    private static $many_many = [
        // Note : Currently unversioned, take care
        'Attachments' => Attachment::class,
    ];

    private static $owns = [
        'Attachments',
    ];
}
