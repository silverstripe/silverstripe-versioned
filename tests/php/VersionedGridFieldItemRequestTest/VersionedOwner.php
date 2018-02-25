<?php

namespace SilverStripe\Versioned\Tests\VersionedGridFieldItemRequestTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin RecursivePublishable
 * @mixin Versioned
 * @property int $RelatedID
 */
class VersionedOwner extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedGridFieldItemRequestTest_VersionedOwner';

    private static $extensions = [
        Versioned::class,
    ];

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $owns = [
        'Related',
    ];

    private static $has_one = [
        'Related' => VersionedObject::class,
    ];
}
