<?php

namespace SilverStripe\Versioned\Tests\RecursivePublishablePermissionTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin RecursivePublishable
 * @mixin Versioned
 * @property string $Title
 * @method PublishOwner Owner()
 */
class RestrictedOwned extends DataObject implements TestOnly
{
    private static $table_name = 'RecursivePublishablePermissionTest_RestrictedOwned';

    private static $extensions = [
        Versioned::class,
    ];

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_one = [
        'Owner' => PublishOwner::class,
    ];

    public function canEdit($member = null)
    {
        return true;
    }

    public function canPublish($member = null)
    {
        return Permission::checkMember($member, 'OWNED_PUBLISH');
    }
}
