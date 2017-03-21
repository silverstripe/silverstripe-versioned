<?php

namespace SilverStripe\Versioned\Tests\VersionedOwnershipTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Object which is owned by a has_one object
 *
 * @mixin Versioned
 */
class RelatedMany extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionedOwnershipTest_RelatedMany';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $has_one = [
        'Page' => Subclass::class,
    ];

    private static $owned_by = [
        'Page'
    ];
}
