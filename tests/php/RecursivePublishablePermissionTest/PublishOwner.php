<?php

namespace SilverStripe\Versioned\Tests\RecursivePublishablePermissionTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin RecursivePublishable
 * @mixin Versioned
 * @property string $Title
 * @property int $OwnedID
 * @method RestrictedOwned Owned()
 * @method HasManyList<RestrictedOwned> Extras()
 */
class PublishOwner extends DataObject implements TestOnly
{
    private static $table_name = 'RecursivePublishablePermissionTest_PublishOwner';

    private static $extensions = [
        Versioned::class,
    ];

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_one = [
        'Owned' => RestrictedOwned::class,
    ];

    private static $has_many = [
        'Extras' => RestrictedOwned::class . '.Owner',
    ];

    private static $owns = [
        'Owned',
        'Extras',
    ];

    public function canView($member = null)
    {
        return true;
    }

    public function canCreate($member = null, $context = [])
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return Permission::checkMember($member, ['OWNER_PUBLISH', 'OWNED_PUBLISH']);
    }
}
