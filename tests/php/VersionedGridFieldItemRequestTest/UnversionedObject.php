<?php

namespace SilverStripe\Versioned\Tests\VersionedGridFieldItemRequestTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;

/**
 * @mixin RecursivePublishable
 */
class UnversionedObject extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedGridFieldItemRequestTest_UnversionedObject';

    private static $db = [
        'Title' => 'Varchar',
    ];
}
