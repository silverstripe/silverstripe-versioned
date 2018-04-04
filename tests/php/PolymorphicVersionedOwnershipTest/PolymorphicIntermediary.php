<?php

namespace SilverStripe\Versioned\Tests\PolymorphicVersionedOwnershipTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * Sits between polymorphicowner and polymorphicowned
 *
 * @mixin Versioned|RecursivePublishable
 */
class PolymorphicIntermediary extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedTest_PolymorphicIntermediary';

    private static $extensions = [
        Versioned::class,
    ];

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_many = [
        'TheOwners' => PolymorphicOwner::class,
        'TheOwned' => PolymorphicOwned::class,
    ];

    private static $owns = [
        'TheOwned',
    ];

    /**
     * Explicit owned_by required since PolymorphicOwner.owns doesn't point back to this directly
     */
    private static $owned_by = [
        'TheOwners',
    ];
}
