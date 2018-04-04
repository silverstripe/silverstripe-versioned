<?php

namespace SilverStripe\Versioned\Tests\PolymorphicVersionedOwnershipTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned|RecursivePublishable
 */
class PolymorphicOwner extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedTest_PolymorphicOwner';

    private static $extensions = [
        Versioned::class,
    ];

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_one = [
        'Joins' => DataObject::class,
    ];

    private static $owns = [
        'Joins',
    ];
}
