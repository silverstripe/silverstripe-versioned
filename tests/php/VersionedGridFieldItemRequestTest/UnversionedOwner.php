<?php

namespace SilverStripe\Versioned\Tests\VersionedGridFieldItemRequestTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;

/**
 * @mixin RecursivePublishable
 * @property int $RelatedID
 */
class UnversionedOwner extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedGridFieldItemRequestTest_UnversionedOwner';

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
