<?php

namespace SilverStripe\Versioned\Tests\VersionedGridFieldItemRequestTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Staged\RecursivePublishable;
use SilverStripe\Versioned\Mode\Versioned;

/**
 * @mixin RecursivePublishable
 * @mixin Versioned
 */
class VersionedObject extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedGridFieldItemRequestTest_VersionedObject';

    private static $extensions = [
        Versioned::class,
    ];

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_many = [
        'UnversionedOwners' => UnversionedOwner::class,
        'VersionedOwners' => VersionedOwner::class,
    ];
}
