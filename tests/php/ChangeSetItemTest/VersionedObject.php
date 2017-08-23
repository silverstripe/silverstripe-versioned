<?php

namespace SilverStripe\Versioned\Tests\ChangeSetItemTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class VersionedObject extends DataObject implements TestOnly
{
    private static $table_name = 'ChangeSetItemTest_Versioned';

    private static $db = [
        'Foo' => 'Int'
    ];

    private static $extensions = [
        Versioned::class
    ];

    public function canEdit($member = null)
    {
        return true;
    }
}
