<?php

namespace SilverStripe\Versioned\Tests\PolymorphicVersionedOwnershipTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned|RecursivePublishable
 */
class PolymorphicOwned extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedTest_PolymorphicOwned';

    private static $extensions = [
        Versioned::class,
    ];

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_one = [
        'JoinedBy' => DataObject::class,
    ];

    /**
     * Explicit owned_by required here, because we don't know which parent class to revers owns from
     */
    private static $owned_by = [
        'JoinedBy',
    ];
}
