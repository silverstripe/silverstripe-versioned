<?php

namespace SilverStripe\Versioned\Mode\Traits;

use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Mode\Versioned;
use SilverStripe\Versioned\Mode\ReadingMode;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;

trait VersionedCanChecksTrait
{
    /**
     * This function should return true if the current user can publish this record.
     * It can be overloaded to customise the security model for an application.
     *
     * Denies permission if any of the following conditions is true:
     * - canPublish() on any extension returns false
     * - canEdit() returns false
     *
     * @param Member $member
     * @return bool True if the current user can publish this record.
     */
    public function canPublish($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if (Permission::checkMember($member, "ADMIN")) {
            return true;
        }

        // Standard mechanism for accepting permission changes from extensions
        $owner = $this->owner;
        $extended = $owner->extendedCan('canPublish', $member);
        if ($extended !== null) {
            return $extended;
        }

        // Default to relying on edit permission
        return $owner->canEdit($member);
    }

    protected function extendCanPublish()
    {
        // prevent canPublish() from extending itself
        return null;
    }

    /**
     * Check if the current user can delete this record from live
     *
     * @param null $member
     * @return mixed
     */
    public function canUnpublish($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if (Permission::checkMember($member, "ADMIN")) {
            return true;
        }

        // Standard mechanism for accepting permission changes from extensions
        $owner = $this->owner;
        $extended = $owner->extendedCan('canUnpublish', $member);
        if ($extended !== null) {
            return $extended;
        }

        // Default to relying on canPublish
        return $owner->canPublish($member);
    }

    protected function extendCanUnpublish()
    {
        // prevent canUnpublish() extending itself
        return null;
    }

    /**
     * Check if the current user is allowed to archive this record.
     *
     * We're intentionally using the canDelete check for archiving,
     * since there's no concept of "deleting" a versioned record
     * and having separate permission checks was confusing and easy
     * to forget.
     */
    public function canDelete($member = null): ?bool
    {
        // If the user isn't allowed to unpublish, they're definitely
        // not allowed to archive live content.
        if ($this->hasStages() && $this->isPublished() && !$this->getOwner()->canUnpublish($member)) {
            return false;
        }
        return null;
    }

    /**
     * Check if the user can revert this record to live
     *
     * @param Member $member
     * @return bool
     */
    public function canRevertToLive($member = null)
    {
        $owner = $this->owner;

        // Can't revert if not on live
        if (!$owner->isPublished()) {
            return false;
        }

        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if (Permission::checkMember($member, "ADMIN")) {
            return true;
        }

        // Standard mechanism for accepting permission changes from extensions
        $extended = $owner->extendedCan('canRevertToLive', $member);
        if ($extended !== null) {
            return $extended;
        }

        // Default to canEdit
        return $owner->canEdit($member);
    }

    protected function extendCanRevertToLive()
    {
        // Prevent canRevertToLive() extending itself
        return null;
    }

    /**
     * Check if the user can restore this record to draft
     *
     * @param Member $member
     * @return bool
     */
    public function canRestoreToDraft($member = null)
    {
        $owner = $this->owner;

        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if (Permission::checkMember($member, "ADMIN")) {
            return true;
        }

        // Standard mechanism for accepting permission changes from extensions
        $extended = $owner->extendedCan('canRestoreToDraft', $member);
        if ($extended !== null) {
            return $extended;
        }

        // Default to canEdit
        return $owner->canEdit($member);
    }

    protected function extendcanRestoreToDraft()
    {
        // Prevent canRestoreToDraft() extending itself
        return null;
    }

    /**
     * Extend permissions to include additional security for objects that are not published to live.
     *
     * @param Member $member
     * @return bool|null
     */
    protected function canView($member = null)
    {
        // Invoke default version-gnostic canView
        if ($this->owner->canViewVersioned($member) === false) {
            return false;
        }
        return null;
    }

    /**
     * Determine if there are any additional restrictions on this object for the given reading version.
     *
     * Override this in a subclass to customise any additional effect that Versioned applies to canView.
     *
     * This is expected to be called by canView, and thus is only responsible for denying access if
     * the default canView would otherwise ALLOW access. Thus it should not be called in isolation
     * as an authoritative permission check.
     *
     * This has the following extension points:
     *  - canViewDraft is invoked if Mode = stage and Stage = stage
     *  - canViewArchived is invoked if Mode = archive
     *
     * @param Member $member
     * @return bool False is returned if the current viewing mode denies visibility
     */
    public function canViewVersioned($member = null)
    {
        // Bypass when live stage
        $owner = $this->owner;

        // Bypass if site is unsecured
        if (!Versioned::get_draft_site_secured()) {
            return true;
        }

        // Get reading mode from source query (or current mode)
        $readingParams = $owner->getSourceQueryParams()
            // Guess record mode from current reading mode instead
            ?: ReadingMode::toDataQueryParams(static::get_reading_mode());

        // If this is the live record we can view it
        if (isset($readingParams["Versioned.mode"])
            && $readingParams["Versioned.mode"] === 'stage'
            && $readingParams["Versioned.stage"] === Versioned::LIVE
        ) {
            return true;
        }

        // Bypass if record doesn't have a live stage
        if (!$this->hasStages()) {
            return true;
        }

        // If we weren't definitely loaded from live, and we can't view non-live content, we need to
        // check to make sure this version is the live version and so can be viewed
        $latestVersion = Versioned::get_versionnumber_by_stage($this->owner->baseClass(), Versioned::LIVE, $owner->ID);
        if ($latestVersion == $owner->Version) {
            // Even if this is loaded from a non-live stage, this is the live version
            return true;
        }

        // If stages are synchronised treat this as the live stage
        if (!$this->stagesDiffer()) {
            return true;
        }

        // Extend versioned behaviour
        $extended = $owner->extendedCan('canViewNonLive', $member);
        if ($extended !== null) {
            return (bool)$extended;
        }

        // Fall back to default permission check
        $permissions = Config::inst()->get(get_class($owner), 'non_live_permissions');
        $check = Permission::checkMember($member, $permissions);
        return (bool)$check;
    }

    /**
     * Determines canView permissions for the latest version of this object on a specific stage.
     * Usually the stage is read from {@link Versioned::current_stage()}.
     *
     * This method should be invoked by user code to check if a record is visible in the given stage.
     *
     * This method should not be called via ->extend('canViewStage'), but rather should be
     * overridden in the extended class.
     *
     * @param string $stage
     * @param Member $member
     * @return bool
     */
    public function canViewStage($stage = Versioned::LIVE, $member = null)
    {
        return static::withVersionedMode(function () use ($stage, $member) {
            Versioned::set_stage($stage);

            $owner = $this->owner;
            $baseClass = DataObject::getSchema()->baseDataClass($owner);
            $versionFromStage = DataObject::get($baseClass)->byID($owner->ID);

            return $versionFromStage ? $versionFromStage->canView($member) : false;
        });
    }
}
