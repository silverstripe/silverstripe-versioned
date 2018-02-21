<?php

namespace SilverStripe\Versioned\Tests\ChangeSetItemTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class UnstagedObject extends DataObject implements TestOnly
{
    private static $table_name = 'ChangeSetItemTest_UnstagedObject';

    private static $db = [
        'Foo' => 'Int'
    ];

    private static $extensions = [
        Versioned::class . '.versioned',
    ];

    public function canEdit($member = null)
    {
        return true;
    }

    public function canPublish($member = null)
    {
        // Should be ignored
        return false;
    }
}
