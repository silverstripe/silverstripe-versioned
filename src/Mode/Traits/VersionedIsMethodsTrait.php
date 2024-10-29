<?php

namespace SilverStripe\Versioned\Mode\Traits;

use SilverStripe\Versioned\Mode\Versioned;

trait VersionedIsMethodsTrait
{
    /**
     * Returns whether the current record is the latest one.
     *
     * @see get_latest_version()
     * @see latestPublished
     *
     * @return boolean
     */
    public function isLatestVersion()
    {
        $owner = $this->owner;
        if (!$owner->isInDB()) {
            return false;
        }

        $version = static::get_latest_version($this->owner->baseClass(), $owner->ID);
        return ($version->Version == $owner->Version);
    }

    /**
     * Returns whether the current record's version is the current live/published version
     *
     * @return bool
     */
    public function isLiveVersion()
    {
        $id = $this->owner->ID ?: $this->owner->OldID;
        if (!$id || !$this->isPublished()) {
            return false;
        }

        $liveVersionNumber = static::get_versionnumber_by_stage($this->owner, Versioned::LIVE, $id);
        return $liveVersionNumber == $this->owner->Version;
    }

    /**
     * Returns whether the current record's version is the current draft/modified version
     *
     * @return bool
     */
    public function isLatestDraftVersion()
    {
        $id = $this->owner->ID ?: $this->owner->OldID;
        if (!$id || !$this->isOnDraft()) {
            return false;
        }

        $draftVersionNumber = static::get_versionnumber_by_stage($this->owner, Versioned::DRAFT, $id);
        return $draftVersionNumber == $this->owner->Version;
    }

    /**
     * Check if this record exists on live
     * On objects with only 1 stage, check if the record exists on that stage.
     *
     * @return bool
     */
    public function isPublished()
    {
        $id = $this->owner->ID ?: $this->owner->OldID;
        if (!$id) {
            return false;
        }

        // Non-staged objects are considered "published" if saved
        if (!$this->hasStages()) {
            return $this->isOnDraft();
        }

        $liveVersion = static::get_versionnumber_by_stage($this->owner, Versioned::LIVE, $id);
        $isPublished = (bool) $liveVersion;

        $this->owner->extend('updateIsPublished', $isPublished);

        return (bool) $isPublished;
    }

    /**
     * Check if page doesn't exist on any stage, but used to be
     *
     * @return bool
     */
    public function isArchived()
    {
        $owner = $this->owner;
        $id = $owner->ID ?: $owner->OldID;
        $isArchived = $id && !$this->isOnDraft() && !$this->isPublished();

        $owner->invokeWithExtensions('updateIsArchived', $isArchived);

        return (bool) $isArchived;
    }

    /**
     * Check if this record exists on the draft stage.
     * On objects with only 1 stage, check if the record exists on that stage.
     *
     * @return bool
     */
    public function isOnDraft()
    {
        $id = $this->owner->ID ?: $this->owner->OldID;
        if (!$id) {
            return false;
        }

        $draftVersion = static::get_versionnumber_by_stage($this->owner, Versioned::DRAFT, $id);
        $isOnDraft = (bool) $draftVersion;

        $this->owner->extend('updateIsOnDraft', $isOnDraft);

        return (bool) $isOnDraft;
    }

    /**
     * Compares current draft with live version, and returns true if no draft version of this page exists  but the page
     * is still published (eg, after triggering "Delete from draft site" in the CMS).
     *
     * @return bool
     */
    public function isOnLiveOnly()
    {
        return $this->isPublished() && !$this->isOnDraft();
    }

    /**
     * Compares current draft with live version, and returns true if no live version exists, meaning the page was never
     * published.
     *
     * @return bool
     */
    public function isOnDraftOnly()
    {
        return $this->isOnDraft() && !$this->isPublished();
    }

    /**
     * Compares current draft with live version, and returns true if these versions differ, meaning there have been
     * unpublished changes to the draft site.
     *
     * @return bool
     */
    public function isModifiedOnDraft()
    {
        return $this->isOnDraft() && $this->stagesDiffer();
    }
}
