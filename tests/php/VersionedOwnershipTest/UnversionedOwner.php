<?php

namespace SilverStripe\Versioned\Tests\VersionedOwnershipTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;

/**
 * @mixin RecursivePublishable
 */
class UnversionedOwner extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_many = [
        'Books' => OwnedByUnversioned::class,
    ];

    private static $owns = [
        'Books',
    ];

    private static $table_name = 'VersionedOwnershipTest_UnversionedOwner';
}
