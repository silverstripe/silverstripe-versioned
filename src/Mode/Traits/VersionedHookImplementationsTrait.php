<?php

namespace SilverStripe\Versioned\Mode\Traits;

use SilverStripe\Forms\FieldList;
use SilverStripe\Versioned\Mode\Versioned;
use SilverStripe\ORM\DataList;

trait VersionedHookImplementationsTrait
{
    /**
     *
     */
    protected function onAfterWrite()
    {
        $this->setNextWriteWithoutVersion(false);
    }

    /**
     * If a write was skipped, then we need to ensure that we don't leave a
     * migrateVersion() value lying around for the next write.
     */
    protected function onAfterSkippedWrite()
    {
        $this->setMigratingVersion(null);
    }

    protected function onAfterDelete()
    {
        // Create deleted record for current stage
        $this->createDeletedVersion(static::get_stage());
    }

    /**
     * Hook into {@link Hierarchy::prepopulateTreeDataCache}.
     *
     * @param DataList|array $recordList The list of records to prepopulate caches for. Null for all records.
     * @param array $options A map of hints about what should be cached. "numChildrenMethod" and
     *                       "childrenMethod" are allowed keys.
     */
    protected function onPrepopulateTreeDataCache($recordList = null, array $options = [])
    {
        $idList = is_array($recordList) ? $recordList :
            ($recordList instanceof DataList ? $recordList->column('ID') : null);
        Versioned::prepopulate_versionnumber_cache($this->owner->baseClass(), Versioned::DRAFT, $idList);
        Versioned::prepopulate_versionnumber_cache($this->owner->baseClass(), Versioned::LIVE, $idList);
    }

    /**
     * @param array $labels
     */
    protected function updateFieldLabels(&$labels)
    {
        $labels['Versions'] = _t(__CLASS__ . '.has_many_Versions', 'Versions', 'Past Versions of this record');
    }

    /**
     * @param FieldList $fields
     */
    protected function updateCMSFields(FieldList $fields)
    {
        // remove the version field from the CMS as this should be left
        // entirely up to the extension (not the cms user).
        $fields->removeByName('Version');
    }

    /**
     * Ensure version ID is reset to 0 on duplicate
     *
     * @param DataObject $source Record this was duplicated from
     * @param bool $doWrite
     */
    protected function onBeforeDuplicate($source, $doWrite)
    {
        $this->owner->Version = 0;
    }

    protected function onFlushCache()
    {
        Versioned::$cache_versionnumber = [];
        $this->versionModifiedCache = [];
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific.
     *
     * @return string
     */
    protected function cacheKeyComponent()
    {
        return 'versionedmode-' . static::get_reading_mode();
    }
}
