<?php

namespace SilverStripe\Versioned\Tests\VersionedOwnershipTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * Simple versioned dataobject
 *
 * @property string $Title
 * @property int $ParentID
 * @method UnversionedOwner Parent()
 * @mixin RecursivePublishable
 * @mixin Versioned
 */
class OwnedByUnversioned extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionedOwnershipTest_OwnedByUnversioned';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $has_one = [
        'Parent' => UnversionedOwner::class,
    ];
}
