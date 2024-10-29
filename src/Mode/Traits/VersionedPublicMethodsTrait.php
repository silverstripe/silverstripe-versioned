<?php

namespace SilverStripe\Versioned\Mode\Traits;

use SilverStripe\Versioned\Mode\Versioned;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Versioned\Staged\RecursiveStagesInterface;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Config\Config;
use SilverStripe\Versioned\Versioned\Versioned_Version;
use SilverStripe\Core\ClassInfo;
use InvalidArgumentException;
use SilverStripe\Versioned\Mode\DataDifferencer;
use SilverStripe\Versioned\Mode\ReadingMode;

trait VersionedPublicMethodsTrait
{
    /**
     * Get this record at a specific version
     *
     * @param int|string|null $from Version or stage to get at. Null mean returns self object
     * @return Versioned|DataObject
     */
    public function getAtVersion($from)
    {
        // Null implies return current version
        if (is_null($from)) {
            return $this->owner;
        }

        $baseClass = $this->owner->baseClass();
        $id = $this->owner->ID ?: $this->owner->OldID;

        // By version number
        if (is_numeric($from)) {
            return Versioned::get_version($baseClass, $id, $from);
        }

        // By stage
        return Versioned::get_by_stage($baseClass, $from)->byID($id);
    }

    /**
     * Perform a write without affecting the version table.
     *
     * @return int The ID of the record
     */
    public function writeWithoutVersion()
    {
        $this->setNextWriteWithoutVersion(true);

        return $this->owner->write();
    }

    /**
     * Check if next write is without version
     *
     * @return bool
     */
    public function getNextWriteWithoutVersion()
    {
        return $this->owner->getField(Versioned::NEXT_WRITE_WITHOUT_VERSIONED);
    }

    /**
     * Set if next write should be without version or not
     *
     * @param bool $flag
     * @return DataObject owner
     */
    public function setNextWriteWithoutVersion($flag)
    {
        return $this->owner->setField(Versioned::NEXT_WRITE_WITHOUT_VERSIONED, $flag);
    }

    /**
     * Check if delete() should write _Version rows or not
     *
     * @return bool
     */
    public function getDeleteWritesVersion()
    {
        return !$this->owner->getField(Versioned::DELETE_WRITES_VERSION_DISABLED);
    }

    /**
     * Set if delete() should write _Version rows
     *
     * @param bool $flag
     * @return DataObject owner
     */
    public function setDeleteWritesVersion($flag)
    {
        return $this->owner->setField(Versioned::DELETE_WRITES_VERSION_DISABLED, !$flag);
    }

    /**
     * Get version migrated to
     *
     * @return int|null
     */
    public function getMigratingVersion()
    {
        return $this->owner->getField(Versioned::MIGRATING_VERSION);
    }

    /**
     * Set the migrating version.
     *
     * @param string $version The version.
     * @return DataObject Owner
     */
    public function setMigratingVersion($version)
    {
        return $this->owner->setField(Versioned::MIGRATING_VERSION, $version);
    }

    /**
     * Helper method to safely suppress delete callback
     *
     * @param callable $callback
     * @return mixed Result of $callback()
     */
    protected function suppressDeletedVersion($callback)
    {
        $original = $this->getDeleteWritesVersion();
        try {
            $this->setDeleteWritesVersion(false);
            return $callback();
        } finally {
            $this->setDeleteWritesVersion($original);
        }
    }

    /**
     * Determine if a class is supporting the Versioned extensions (e.g.
     * $table_Versions does exists).
     *
     * @param string $class Class name
     * @return boolean
     */
    public function canBeVersioned($class)
    {
        return ClassInfo::exists($class)
            && is_subclass_of($class, DataObject::class)
            && DataObject::getSchema()->classHasTable($class);
    }

    /**
     * Check if a certain table has the 'Version' field.
     *
     * @param string $table Table name
     *
     * @return boolean Returns false if the field isn't in the table, true otherwise
     */
    public function hasVersionField($table)
    {
        // Base table has version field
        $class = DataObject::getSchema()->tableClass($table);
        return $class === DataObject::getSchema()->baseDataClass($class);
    }

    /**
     * @param string $table
     *
     * @return string
     */
    public function extendWithSuffix($table)
    {
        $owner = $this->owner;
        $versionableExtensions = (array)$owner->config()->get('versionableExtensions');

        if (count($versionableExtensions ?? [])) {
            foreach ($versionableExtensions as $versionableExtension => $suffixes) {
                if ($owner->hasExtension($versionableExtension)) {
                    /** @var VersionableExtension|Extension $ext */
                    $ext = $owner->getExtensionInstance($versionableExtension);
                    try {
                        $ext->setOwner($owner);
                        $table = $ext->extendWithSuffix($table);
                    } finally {
                        $ext->clearOwner();
                    }
                }
            }
        }

        return $table;
    }

    /**
     * Determines if the current draft version is the same as live or rather, that there are no outstanding draft changes
     *
     * @return bool
     */
    public function latestPublished()
    {
        $id = $this->owner->ID ?: $this->owner->OldID;
        if (!$id) {
            return false;
        }
        if (!$this->hasStages()) {
            return true;
        }
        $draftVersion = Versioned::get_versionnumber_by_stage($this->owner, Versioned::DRAFT, $id);
        $liveVersion = Versioned::get_versionnumber_by_stage($this->owner, Versioned::LIVE, $id);
        return $draftVersion === $liveVersion;
    }

    /**
     * Publishes this object to Live, but doesn't publish owned objects.
     *
     * User code should call {@see canPublish()} prior to invoking this method.
     *
     * @return bool True if publish was successful
     */
    public function publishSingle()
    {
        $owner = $this->owner;
        // get the last published version
        $original = null;
        if ($this->isPublished()) {
            $original = Versioned::get_by_stage($owner->baseClass(), Versioned::LIVE)
                ->byID($owner->ID);
        }

        // Publish it
        $owner->invokeWithExtensions('onBeforePublish', $original);
        $owner->writeToStage(Versioned::LIVE);
        $owner->invokeWithExtensions('onAfterPublish', $original);
        return true;
    }

    /**
     * Removes the record from both live and stage
     *
     * User code should call {@see canDelete()} prior to invoking this method.
     *
     * @return bool Success
     */
    public function doArchive()
    {
        $owner = $this->owner;
        $owner->invokeWithExtensions('onBeforeArchive', $this);
        $owner->deleteFromChangeSets();
        // Unpublish without creating deleted version
        $this->suppressDeletedVersion(function () use ($owner) {
            $owner->doUnpublish();
        });
        // Create deleted version in both stages
        $this->createDeletedVersion([
            Versioned::LIVE,
            Versioned::DRAFT,
        ]);
        $this->suppressDeletedVersion(function () use ($owner) {
            $owner->deleteFromStage(Versioned::DRAFT);
        });
        $owner->invokeWithExtensions('onAfterArchive', $this);
        return true;
    }

    /**
     * Removes this record from the live site
     *
     * User code should call {@see canUnpublish()} prior to invoking this method.
     *
     * @return bool Flag whether the unpublish was successful
     */
    public function doUnpublish()
    {
        $owner = $this->owner;
        // Skip if this record isn't saved
        if (!$owner->isInDB()) {
            return false;
        }

        // Skip if this record isn't on live
        if (!$owner->isPublished()) {
            return false;
        }

        $owner->invokeWithExtensions('onBeforeUnpublish');

        // Modify in isolated mode
        Versioned::withVersionedMode(function () use ($owner) {
            Versioned::set_stage(Versioned::LIVE);

            // Re-fetch the current DataObject to ensure we have data from the LIVE stage
            // This is particularly relevant for DataObject's in a modified state so that
            // any delete extensions have the correct database record values
            $obj = $owner::get()->byID($owner->ID);
            if (!$obj) {
                return;
            }
            $obj->setDeleteWritesVersion($owner->getDeleteWritesVersion());
            $obj->delete();
        });

        $owner->invokeWithExtensions('onAfterUnpublish');
        return true;
    }

    /**
     * Determine if this object is published, and has any published owners.
     * If this is true, a warning should be shown before this is published.
     *
     * Note: This method returns false if the object itself is unpublished,
     * since owners are only considered on the same stage as the record itself.
     *
     * @return bool
     */
    public function hasPublishedOwners()
    {
        if (!$this->isPublished()) {
            return false;
        }
        // Count live owners
        $baseClass = $this->owner->baseClass();

        /** @var Versioned|RecursivePublishable|DataObject $liveRecord */
        $liveRecord = Versioned::get_by_stage($baseClass, Versioned::LIVE)->byID($this->owner->ID);
        return $liveRecord->findOwners(false)->count() > 0;
    }

    /**
     * Revert the draft changes: replace the draft content with the content on live
     *
     * User code should call {@see canRevertToLive()} prior to invoking this method.
     *
     * @return bool True if the revert was successful
     */
    public function doRevertToLive()
    {
        $owner = $this->owner;
        $owner->invokeWithExtensions('onBeforeRevertToLive');
        $owner->rollbackRecursive(Versioned::LIVE);
        $owner->invokeWithExtensions('onAfterRevertToLive');
        return true;
    }

    /**
     * Move a database record from one stage to the other.
     *
     * @param int|string|null $fromStage Place to copy from.  Can be either a stage name or a version number.
     * Null copies current object to stage
     * @param string $toStage Place to copy to.  Must be a stage name.
     */
    public function copyVersionToStage($fromStage, $toStage)
    {
        $owner = $this->owner;
        $owner->invokeWithExtensions('onBeforeVersionedPublish', $fromStage, $toStage);

        // Get at specific version
        $from = $this->getAtVersion($fromStage);
        if (!$from) {
            $baseClass = $owner->baseClass();
            throw new InvalidArgumentException("Can't find {$baseClass}#{$owner->ID} in stage {$fromStage}");
        }

        $from->writeToStage($toStage);
        $owner->invokeWithExtensions('onAfterVersionedPublish', $fromStage, $toStage);
    }

    /**
     * Compare two stages to see if they're different.
     *
     * Only checks the version numbers, not the actual content.
     *
     * @return bool
     */
    public function stagesDiffer()
    {
        $id = $this->owner->ID ?: $this->owner->OldID;
        if (!$id || !$this->hasStages()) {
            return false;
        }

        $draftVersion = Versioned::get_versionnumber_by_stage($this->owner, Versioned::DRAFT, $id);
        $liveVersion = Versioned::get_versionnumber_by_stage($this->owner, Versioned::LIVE, $id);
        $stagesDiffer = $draftVersion !== $liveVersion;

        $this->owner->extend('updateStagesDiffer', $stagesDiffer);

        return (bool) $stagesDiffer;
    }

    /**
     * Determine if content differs on stages including nested objects
     * 'owns' configuration drives the relationship traversal
     */
    public function stagesDifferRecursive(): bool
    {
        $service = Injector::inst()->get(RecursiveStagesInterface::class);

        return $service->stagesDifferRecursive($this->owner);
    }

    /**
     * @param string $filter
     * @param string $sort
     * @param string $limit
     * @param string $join Deprecated, use leftJoin($table, $joinClause) instead
     * @param string $having @deprecated 2.2.0 The $having parameter does nothing and will be removed without
     *               equivalent functionality to replace it
     * @return ArrayList<Versioned_Version>
     */
    public function Versions($filter = "", $sort = "", $limit = "", $join = "", $having = "")
    {
        if ($having) {
            Deprecation::withSuppressedNotice(function () {
                $message = 'The $having parameter does nothing and will be removed without equivalent'
                . ' functionality to replace it';
                Deprecation::notice('2.2.0', $message);
            });
        }

        $owner = $this->owner;

        // When an object is not yet in the Database, we can't get its versions
        if (!$owner->isInDB()) {
            return ArrayList::create();
        }

        // Make sure the table names are not postfixed (e.g. _Live)
        $oldMode = Versioned::get_reading_mode();
        Versioned::set_stage(Versioned::DRAFT);

        $list = DataObject::get(DataObject::getSchema()->baseDataClass($owner), $filter, $sort, $join, $limit);

        $query = $list->dataQuery()->query();

        $baseTable = null;
        foreach ($query->getFrom() as $table => $tableJoin) {
            if (is_string($tableJoin) && $tableJoin[0] == '"') {
                $baseTable = str_replace('"', '', $tableJoin ?? '');
            } elseif (is_string($tableJoin) && substr($tableJoin ?? '', 0, 5) != 'INNER') {
                $query->setFrom([
                    $table => "LEFT JOIN \"$table\" ON \"$table\".\"RecordID\"=\"{$baseTable}_Versions\".\"RecordID\""
                        . " AND \"$table\".\"Version\" = \"{$baseTable}_Versions\".\"Version\""
                ]);
            }
            $query->renameTable($table, $table . '_Versions');
        }

        // Add all <basetable>_Versions columns
        foreach (Config::inst()->get(Versioned::class, 'db_for_versions_table') as $name => $type) {
            $query->selectField(sprintf('"%s_Versions"."%s"', $baseTable, $name), $name);
        }

        $query->addWhere([
            "\"{$baseTable}_Versions\".\"RecordID\" = ?" => $owner->ID
        ]);
        $query->setOrderBy(($sort) ? $sort
            : "\"{$baseTable}_Versions\".\"LastEdited\" DESC, \"{$baseTable}_Versions\".\"Version\" DESC");

        $records = $query->execute();
        $versions = new ArrayList();

        foreach ($records as $record) {
            $versions->push(new Versioned_Version($record));
        }

        Versioned::set_reading_mode($oldMode);
        return $versions;
    }

    /**
     * Compare two version, and return the diff between them.
     *
     * @param string $from The version to compare from.
     * @param string $to The version to compare to.
     *
     * @return DataObject
     */
    public function compareVersions($from, $to)
    {
        $owner = $this->owner;
        $baseClass = $this->owner->baseClass();

        $fromRecord = Versioned::get_version($baseClass, $owner->ID, $from);
        $toRecord = Versioned::get_version($baseClass, $owner->ID, $to);

        $diff = new DataDifferencer($fromRecord, $toRecord);

        return $diff->diffedData();
    }


    /**
     * Delete this record from the given stage
     *
     * @param string $stage
     */
    public function deleteFromStage($stage)
    {
        ReadingMode::validateStage($stage);
        $owner = $this->owner;
        Versioned::withVersionedMode(function () use ($stage, $owner) {
            Versioned::set_stage($stage);
            $clone = clone $owner;
            $clone->delete();
        });

        // Fix the version number cache (in case you go delete from stage and then check ExistsOnLive)
        $baseClass = $owner->baseClass();
        Versioned::$cache_versionnumber[$baseClass][$stage][$owner->ID] = null;
    }

    /**
     * Write the given record to the given stage.
     * Note: If writing to live, this will write to stage as well.
     *
     * @param string $stage
     * @param boolean $forceInsert
     * @return int The ID of the record
     */
    public function writeToStage($stage, $forceInsert = false)
    {
        ReadingMode::validateStage($stage);
        $owner = $this->owner;
        return Versioned::withVersionedMode(function () use ($stage, $forceInsert, $owner) {
            $oldParams = $owner->getSourceQueryParams();
            try {
                // Lazy load and reset version in current stage prior to resetting write stage
                $owner->forceChange();
                $owner->Version = null;

                // Migrate stage prior to write
                Versioned::set_stage($stage);
                $owner->setSourceQueryParam('Versioned.mode', 'stage');
                $owner->setSourceQueryParam('Versioned.stage', $stage);

                // Write
                $owner->invokeWithExtensions('onBeforeWriteToStage', $stage, $forceInsert);
                return $owner->write(false, $forceInsert);
            } finally {
                // Revert global state
                $owner->invokeWithExtensions('onAfterWriteToStage', $stage, $forceInsert);
                $owner->setSourceQueryParams($oldParams);
            }
        });
    }

    /**
     * Recursively rollback draft to the given version. This will also rollback any owned objects
     * at that point in time to the same date. Objects which didn't exist (or weren't attached)
     * to the record at the target point in time will be "unlinked", which dis-associates
     * the record without requiring a hard deletion.
     *
     * @param int|string|null $version Version ID or Versioned::LIVE to rollback from live.
     * Pass in null to rollback to the current object
     * @return DataObject|Versioned The object rolled back
     */
    public function rollbackRecursive($version = null)
    {
        $owner = $this->owner;
        $owner->invokeWithExtensions('onBeforeRollbackRecursive', $version);
        $owner->rollbackSingle($version);

        // Rollback relations on this item (works on unversioned records too)
        $rolledBackOwner = $this->getAtVersion($version);
        if ($rolledBackOwner) {
            $rolledBackOwner->rollbackRelations($version);
        }

        // Unlink any objects disowned as a result of this action
        // I.e. objects which aren't owned anymore by this record, but are by the old draft record
        $rolledBackOwner->unlinkDisownedObjects($rolledBackOwner, Versioned::DRAFT);
        $rolledBackOwner->invokeWithExtensions('onAfterRollbackRecursive', $version);

        // Get rolled back version on draft
        return $this->getAtVersion(Versioned::DRAFT);
    }

    /**
     * Rollback draft to a given version
     *
     * @param int|string|null $version Version ID or Versioned::LIVE to rollback from live.
     * Null to rollback current owner object.
     */
    public function rollbackSingle($version)
    {
        // Validate $version and safely cast
        if (isset($version) && !is_numeric($version) && $version !== Versioned::LIVE) {
            throw new InvalidArgumentException("Invalid rollback source version $version");
        }
        if (isset($version) && is_numeric($version)) {
            $version = (int)$version;
        }
        // Copy version between stage
        $owner = $this->owner;
        $owner->invokeWithExtensions('onBeforeRollbackSingle', $version);
        $owner->copyVersionToStage($version, Versioned::DRAFT);
        $owner->invokeWithExtensions('onAfterRollbackSingle', $version);
    }
}
