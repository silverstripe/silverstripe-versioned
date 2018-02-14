<?php

namespace SilverStripe\Versioned\Tests\ChangeSetTest;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Security\Permission;

/**
 * Provides a set of targettable permissions for tested models
 *
 * @mixin Versioned
 * @mixin DataObject
 */
trait Permissions
{
    public static $can_overrides = [];

    public function canEdit($member = null)
    {
        return $this->can(__FUNCTION__, $member);
    }

    public function canDelete($member = null)
    {
        return $this->can(__FUNCTION__, $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return $this->can(__FUNCTION__, $member, $context);
    }

    public function canPublish($member = null, $context = [])
    {
        return $this->can(__FUNCTION__, $member, $context);
    }

    public function canUnpublish($member = null, $context = [])
    {
        return $this->can(__FUNCTION__, $member, $context);
    }

    public function can($perm, $member = null, $context = [])
    {
        // Check object overrides
        if (isset(static::$can_overrides[$this->ID][$perm])) {
            return static::$can_overrides[$this->ID][$perm];
        }

        $perms = [
            "PERM_{$perm}",
            'CAN_ALL',
        ];
        return Permission::checkMember($member, $perms);
    }

    /**
     * Set can override
     *
     * @param string $perm
     * @param bool $can
     */
    public function setCan($perm, $can)
    {
        static::$can_overrides[$this->ID][$perm] = (bool)$can;
    }

    /**
     * Reset overrides
     */
    public static function reset()
    {
        static::$can_overrides = [];
    }
}
