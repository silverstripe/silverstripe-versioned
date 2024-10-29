<?php

namespace SilverStripe\Versioned\Mode\Traits;

use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\DataQuery;
use SilverStripe\Versioned\Versioned\VersionableExtension;
use LogicException;
use SilverStripe\Core\Config\Config;
use SilverStripe\Versioned\Mode\Versioned;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use SilverStripe\ORM\FieldType\DBDatetime;
use InvalidArgumentException;

trait VersionedAugmentSomethingTrait
{
    protected function augmentWrite(&$manipulation)
    {
        // get Version number from base data table on write
        $version = null;
        $owner = $this->owner;
        $baseDataTable = DataObject::getSchema()->baseDataTable($owner);
        $migratingVersion = $this->getMigratingVersion();
        if (isset($manipulation[$baseDataTable]['fields'])) {
            if ($migratingVersion) {
                $manipulation[$baseDataTable]['fields']['Version'] = $migratingVersion;
            }
            if (isset($manipulation[$baseDataTable]['fields']['Version'])) {
                $version = $manipulation[$baseDataTable]['fields']['Version'];
            }
        }

        // Update all tables
        $thisVersion = null;
        $tables = array_keys($manipulation ?? []);
        foreach ($tables as $table) {
            // Make sure that the augmented write is being applied to a table that can be versioned
            $class = isset($manipulation[$table]['class']) ? $manipulation[$table]['class'] : null;
            if (!$class || !$this->canBeVersioned($class)) {
                unset($manipulation[$table]);
                continue;
            }

            // Get ID field
            $id = $manipulation[$table]['id']
                ? $manipulation[$table]['id']
                : $manipulation[$table]['fields']['ID'];
            if (!$id) {
                throw new InvalidArgumentException(
                    "Couldn't find ID in " . var_export($manipulation[$table], true)
                );
            }

            if ($version < 0 || $this->getNextWriteWithoutVersion()) {
                // Putting a Version of -1 is a signal to leave the version table alone, despite their being no version
                unset($manipulation[$table]['fields']['Version']);
            } else {
                // All writes are to draft, only live affect both
                $stages = !$this->hasStages() || static::get_stage() === Versioned::LIVE
                    ? [Versioned::DRAFT, Versioned::LIVE]
                    : [Versioned::DRAFT];
                $this->augmentWriteVersioned($manipulation, $class, $table, $id, $stages, false);
            }

            // Remove "Version" column from subclasses of baseDataClass
            if (!$this->hasVersionField($table)) {
                unset($manipulation[$table]['fields']['Version']);
            }

            // Grab a version number - it should be the same across all tables.
            if (isset($manipulation[$table]['fields']['Version'])) {
                $thisVersion = $manipulation[$table]['fields']['Version'];
            }

            // If we're editing Live, then write to (table)_Live as well as (table)
            if ($this->hasStages() && static::get_stage() === Versioned::LIVE) {
                $this->augmentWriteStaged($manipulation, $table, $id);
            }
        }

        // Clear the migration flag
        if ($migratingVersion) {
            $this->setMigratingVersion(null);
        }

        // Add the new version # back into the data object, for accessing
        // after this write
        if ($thisVersion !== null) {
            $owner->Version = str_replace("'", "", $thisVersion ?? '');
        }
    }

    /**
     * For lazy loaded fields requiring extra sql manipulation, ie versioning.
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     * @param DataObject $dataObject
     */
    protected function augmentLoadLazyFields(SQLSelect &$query, DataQuery &$dataQuery = null, $dataObject)
    {
        // The VersionedMode local variable ensures that this decorator only applies to
        // queries that have originated from the Versioned object, and have the Versioned
        // metadata set on the query object. This prevents regular queries from
        // accidentally querying the *_Versions tables.
        $versionedMode = $dataObject->getSourceQueryParam('Versioned.mode');
        $modesToAllowVersioning = ['all_versions', 'latest_versions', 'archive', 'version'];
        if (!empty($dataObject->Version) &&
            (!empty($versionedMode) && in_array($versionedMode, $modesToAllowVersioning ?? []))
        ) {
            // This will ensure that augmentSQL will select only the same version as the owner,
            // regardless of how this object was initially selected
            $versionColumn = $this->owner->getSchema()->sqlColumnForField($this->owner, 'Version');
            $dataQuery->where([
                $versionColumn => $dataObject->Version
            ]);
            $dataQuery->setQueryParam('Versioned.mode', 'all_versions');
        }
    }

    protected function augmentDatabase()
    {
        $owner = $this->owner;
        $class = get_class($owner);
        $schema = $owner->getSchema();
        $baseTable = $this->baseTable();
        $classTable = $schema->tableName($owner);

        $isRootClass = $class === $owner->baseClass();

        // Build a list of suffixes whose tables need versioning
        $allSuffixes = [];
        $versionableExtensions = (array)$owner->config()->get('versionableExtensions');
        if (count($versionableExtensions ?? [])) {
            foreach ($versionableExtensions as $versionableExtension => $suffixes) {
                if ($owner->hasExtension($versionableExtension)) {
                    foreach ((array)$suffixes as $suffix) {
                        $allSuffixes[$suffix] = $versionableExtension;
                    }
                }
            }
        }

        // Add the default table with an empty suffix to the list (table name = class name)
        $allSuffixes[''] = null;

        foreach ($allSuffixes as $suffix => $extension) {
            // Check tables for this build
            if ($suffix) {
                $suffixBaseTable = "{$baseTable}_{$suffix}";
                $suffixTable = "{$classTable}_{$suffix}";
            } else {
                $suffixBaseTable = $baseTable;
                $suffixTable = $classTable;
            }

            $fields = $schema->databaseFields($class, false);
            unset($fields['ID']);
            if ($fields) {
                $options = Config::inst()->get($class, 'create_table_options');
                $indexes = $schema->databaseIndexes($class, false);
                $extensionClass = $allSuffixes[$suffix];
                if ($suffix && ($extension = $owner->getExtensionInstance($extensionClass))) {
                    if (!$extension instanceof VersionableExtension) {
                        throw new LogicException(
                            "Extension {$extensionClass} must implement VersionableExtension"
                        );
                    }
                    /** @var Extension&VersionableExtension $extension */
                    // Allow versionable extension to customise table fields and indexes
                    try {
                        $extension->setOwner($owner);
                        if ($extension->isVersionedTable($suffixTable)) {
                            $extension->updateVersionableFields($suffix, $fields, $indexes);
                        }
                    } finally {
                        $extension->clearOwner();
                    }
                }

                // Build _Live table
                if ($this->hasStages()) {
                    $liveTable = $this->stageTable($suffixTable, Versioned::LIVE);
                    DB::require_table($liveTable, $fields, $indexes, false, $options);
                }

                // Build _Versions table
                //Unique indexes will not work on versioned tables, so we'll convert them to standard indexes:
                $nonUniqueIndexes = $this->uniqueToIndex($indexes);
                if ($isRootClass) {
                    // Create table for all versions
                    $versionFields = array_merge(
                        Config::inst()->get(static::class, 'db_for_versions_table'),
                        (array)$fields
                    );
                    $versionIndexes = array_merge(
                        Config::inst()->get(static::class, 'indexes_for_versions_table'),
                        (array)$nonUniqueIndexes
                    );
                } else {
                    // Create fields for any tables of subclasses
                    $versionFields = array_merge(
                        [
                            "RecordID" => "Int",
                            "Version" => "Int",
                        ],
                        (array)$fields
                    );
                    $versionIndexes = array_merge(
                        [
                            'RecordID_Version' => [
                                'type' => 'unique',
                                'columns' => ['RecordID', 'Version']
                            ],
                            'RecordID' => [
                                'type' => 'index',
                                'columns' => ['RecordID'],
                            ],
                            'Version' => [
                                'type' => 'index',
                                'columns' => ['Version'],
                            ],
                        ],
                        (array)$nonUniqueIndexes
                    );
                }

                // Cleanup any orphans
                $this->cleanupVersionedOrphans("{$suffixBaseTable}_Versions", "{$suffixTable}_Versions");

                // Build versions table
                DB::require_table("{$suffixTable}_Versions", $versionFields, $versionIndexes, true, $options);
            } else {
                DB::dont_require_table("{$suffixTable}_Versions");
                if ($this->hasStages()) {
                    $liveTable = $this->stageTable($suffixTable, Versioned::LIVE);
                    DB::dont_require_table($liveTable);
                }
            }
        }
    }

    /**
     * Cleanup orphaned records in the _Versions table
     *
     * @param string $baseTable base table to use as authoritative source of records
     * @param string $childTable Sub-table to clean orphans from
     */
    protected function cleanupVersionedOrphans($baseTable, $childTable)
    {
        // Avoid if disabled
        if ($this->owner->config()->get('versioned_orphans_disabled')) {
            return;
        }

        // Skip if tables are the same (ignore case)
        if (strcasecmp($childTable ?? '', $baseTable ?? '') === 0) {
            return;
        }

        // Skip if child table doesn't exist
        // If it does, ensure query case matches found case
        $tables = DB::get_schema()->tableList();
        if (!array_key_exists(strtolower($childTable ?? ''), $tables ?? [])) {
            return;
        }
        $childTable = $tables[strtolower($childTable)];

        // Select all orphaned version records
        $orphanedQuery = SQLSelect::create()
            ->selectField("\"{$childTable}\".\"ID\"")
            ->setFrom("\"{$childTable}\"");

        // If we have a parent table limit orphaned records
        // to only those that exist in this
        if (array_key_exists(strtolower($baseTable ?? ''), $tables ?? [])) {
            // Ensure we match db table case
            $baseTable = $tables[strtolower($baseTable)];
            $orphanedQuery
                ->addLeftJoin(
                    $baseTable,
                    "\"{$childTable}\".\"RecordID\" = \"{$baseTable}\".\"RecordID\"
					AND \"{$childTable}\".\"Version\" = \"{$baseTable}\".\"Version\""
                )
                ->addWhere("\"{$baseTable}\".\"ID\" IS NULL");
        }

        $count = $orphanedQuery->count();
        if ($count > 0) {
            DB::alteration_message("Removing {$count} orphaned versioned records", "deleted");
            $ids = $orphanedQuery->execute()->column();
            foreach ($ids as $id) {
                DB::prepared_query("DELETE FROM \"{$childTable}\" WHERE \"ID\" = ?", [$id]);
            }
        }
    }

    /**
     * Helper for augmentDatabase() to find unique indexes and convert them to non-unique
     *
     * @param array $indexes The indexes to convert
     * @return array $indexes
     */
    private function uniqueToIndex($indexes)
    {
        foreach ($indexes as &$spec) {
            if ($spec['type'] === 'unique') {
                $spec['type'] = 'index';
            }
        }
        return $indexes;
    }

    /**
     * Generates a ($table)_version DB manipulation and injects it into the current $manipulation
     *
     * @param array $manipulation Source manipulation data
     * @param string $class Class
     * @param string $table Table Table for this class
     * @param int $recordID ID of record to version
     * @param array|string $stages Stage or array of affected stages
     * @param bool $isDelete Set to true of version is created from a deletion
     */
    protected function augmentWriteVersioned(&$manipulation, $class, $table, $recordID, $stages, $isDelete = false)
    {
        $schema = DataObject::getSchema();
        $baseDataClass = $schema->baseDataClass($class);
        $baseDataTable = $schema->tableName($baseDataClass);

        // Set up a new entry in (table)_Versions
        $newManipulation = [
            "command" => "insert",
            "fields" => isset($manipulation[$table]['fields']) ? $manipulation[$table]['fields'] : [],
            "class" => $class,
        ];

        // Add any extra, unchanged fields to the version record.
        $data = DB::prepared_query("SELECT * FROM \"{$table}\" WHERE \"ID\" = ?", [$recordID])->record();
        if ($data) {
            $fields = $schema->databaseFields($class, false);
            if (is_array($fields)) {
                $data = array_intersect_key($data ?? [], $fields);

                foreach ($data as $k => $v) {
                    // If the value is not set at all in the manipulation currently, use the existing value from the database
                    if (!array_key_exists($k, $newManipulation['fields'] ?? [])) {
                        $newManipulation['fields'][$k] = $v;
                    }
                }
            }
        }

        // Ensure that the ID is instead written to the RecordID field
        $newManipulation['fields']['RecordID'] = $recordID;
        unset($newManipulation['fields']['ID']);

        // Generate next version ID to use
        $nextVersion = 0;
        if ($recordID) {
            $nextVersion = DB::prepared_query(
                "SELECT MAX(\"Version\") + 1
				FROM \"{$baseDataTable}_Versions\" WHERE \"RecordID\" = ?",
                [$recordID]
            )->value();
        }
        $nextVersion = $nextVersion ?: 1;

        if ($class === $baseDataClass) {
            // Write AuthorID for baseclass
            if ((Security::getCurrentUser())) {
                $userID = Security::getCurrentUser()->ID;
            } else {
                $userID = 0;
            }
            $wasPublished = (int)in_array(Versioned::LIVE, (array)$stages);
            $wasDraft = (int)in_array(Versioned::DRAFT, (array)$stages);
            $newManipulation['fields'] = array_merge(
                $newManipulation['fields'],
                [
                    'AuthorID' => $userID,
                    'PublisherID' => $wasPublished ? $userID : 0,
                    'WasPublished' => $wasPublished,
                    'WasDraft' => $wasDraft,
                    'WasDeleted' => (int)$isDelete,
                ]
            );

            // Update main table version if not previously known
            if (isset($manipulation[$table]['fields'])) {
                $manipulation[$table]['fields']['Version'] = $nextVersion;
            }
        }

        // Update _Versions table manipulation
        $newManipulation['fields']['Version'] = $nextVersion;
        $manipulation["{$table}_Versions"] = $newManipulation;
    }

    /**
     * Rewrite the given manipulation to update the selected (non-default) stage
     *
     * @param array $manipulation Source manipulation data
     * @param string $table Name of table
     * @param int $recordID ID of record to version
     */
    protected function augmentWriteStaged(&$manipulation, $table, $recordID)
    {
        // If the record has already been inserted in the (table), get rid of it.
        if ($manipulation[$table]['command'] == 'insert') {
            DB::prepared_query(
                "DELETE FROM \"{$table}\" WHERE \"ID\" = ?",
                [$recordID]
            );
        }

        $newTable = $this->stageTable($table, Versioned::get_stage());
        $manipulation[$newTable] = $manipulation[$table];
    }

    /**
     * Adds a WasDeleted=1 version entry for this record, and records any stages
     * the deletion applies to
     *
     * @param string[]|string $stages Stage or array of affected stages
     */
    protected function createDeletedVersion($stages = [])
    {
        // Skip if suppressed by parent delete
        if (!$this->getDeleteWritesVersion()) {
            return;
        }
        // Prepare manipulation
        $baseTable = $this->owner->baseTable();
        $now = DBDatetime::now()->Rfc2822();
        // Ensure all fixed_fields are specified
        $manipulation = [
            $baseTable => [
                'fields' => [
                    'ID' => $this->owner->ID,
                    'LastEdited' => $now,
                    'Created' => $this->owner->Created ?: $now,
                    'ClassName' => $this->owner->ClassName,
                ],
            ],
        ];
        // Prepare "deleted" augment write
        $this->augmentWriteVersioned(
            $manipulation,
            $this->owner->baseClass(),
            $baseTable,
            $this->owner->ID,
            $stages,
            true
        );
        unset($manipulation[$baseTable]);
        $this->owner->extend('augmentWriteDeletedVersion', $manipulation, $stages);
        DB::manipulate($manipulation);
        $this->owner->Version = $manipulation["{$baseTable}_Versions"]['fields']['Version'];
        $this->owner->extend('onAfterVersionDelete');
    }
}
