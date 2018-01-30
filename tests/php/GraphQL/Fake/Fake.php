<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $Name
 * @mixin Versioned
 * @mixin RecursivePublishable
 */
class Fake extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedGraphQLTest_DataObject';

    private static $db = [
        "Name" => "Varchar",
    ];

    private static $extensions = [
        Versioned::class,
    ];

    public function canView($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return true;
    }
}
