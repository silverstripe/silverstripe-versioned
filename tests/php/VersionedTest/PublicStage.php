<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Versioned dataobject with public stage mode
 *
 * @mixin Versioned
 */
class PublicStage extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedTest_PublicStage';

    private static $db = [
        'Title' => 'Varchar'
    ];

    private static $extensions = [
        Versioned::class
    ];

    public function canView($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return true;
    }

    public function canViewVersioned($member = null)
    {
        // All non-live modes are public
        return true;
    }
}
