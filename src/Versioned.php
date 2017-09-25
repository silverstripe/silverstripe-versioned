<?php

namespace SilverStripe\Versioned;

use InvalidArgumentException;
use LogicException;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Resettable;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\TemplateGlobalProvider;

/**
 * The Versioned extension allows your DataObjects to have several versions,
 * allowing you to rollback changes and view history. An example of this is
 * the pages used in the CMS.
 *
 * Note: This extension relies on the object also having the {@see Ownership} extension applied.
 *
 * @property int $Version
 * @property DataObject|RecursivePublishable|Versioned $owner
 */
class Versioned extends DataExtension implements TemplateGlobalProvider, Resettable
{
    /**
     * Versioning mode for this object.
     * Note: Not related to the current versioning mode in the state / session
     * Will be one of 'StagedVersioned' or 'Versioned';
     *
     * @var string
     */
    protected $mode;

    /**
     * The default reading mode
     */
    const DEFAULT_MODE = 'Stage.Live';

    /**
     * Constructor arg to specify that staging is active on this record.
     * 'Staging' implies that 'Versioning' is also enabled.
     */
    const STAGEDVERSIONED = 'StagedVersioned';

    /**
     * Constructor arg to specify that versioning only is active on this record.
     */
    const VERSIONED = 'Versioned';

    /**
     * The Public stage.
     */
    const LIVE = 'Live';

    /**
     * The draft (default) stage
     */
    const DRAFT = 'Stage';

    /**
     * A cache used by get_versionnumber_by_stage().
     * Clear through {@link flushCache()}.
     * version (int)0 means not on this stage.
     *
     * @var array
     */
    protected static $cache_versionnumber;

    /**
     * Cache of version to modified dates for this object
     *
     * @var array
     */
    protected $versionModifiedCache = [];

    /**
     * Current reading mode
     *
     * @var string
     */
    protected static $reading_mode = null;

    /**
     * Field used to hold the migrating version
     */
    const MIGRATING_VERSION = 'MigratingVersion';

    /**
     * Field used to hold flag indicating the next write should be without a new version
     */
    const NEXT_WRITE_WITHOUT_VERSIONED = 'NextWriteWithoutVersioned';

    /**
     * Prevents delete() from creating a _Versioned record (in case this must be deferred)
     * Best used with suppressDeleteVersion()
     */
    const DELETE_WRITES_VERSION_DISABLED = 'DeleteWritesVersionDisabled';

    /**
     * Ensure versioned page doesn't attempt to virtualise these non-db fields
     *
     * @config
     * @var array
     */
    private static $non_virtual_fields = [
        self::MIGRATING_VERSION,
        self::NEXT_WRITE_WITHOUT_VERSIONED,
        self::DELETE_WRITES_VERSION_DISABLED,
    ];

    /**
     * Additional database columns for the new
     * "_Versions" table. Used in {@link augmentDatabase()}
     * and all Versioned calls extending or creating
     * SELECT statements.
     *
     * @var array $db_for_versions_table
     */
    private static $db_for_versions_table = [
        "RecordID" => "Int",
        "Version" => "Int",
        "WasPublished" => "Boolean",
        "WasDeleted" => "Boolean",
        "WasDraft" => "Boolean",
        "AuthorID" => "Int",
        "PublisherID" => "Int"
    ];

    /**
     * @var array
     * @config
     */
    private static $db = [
        'Version' => 'Int'
    ];

    /**
     * Used to enable or disable the prepopulation of the version number cache.
     * Defaults to true.
     *
     * @config
     * @var boolean
     */
    private static $prepopulate_versionnumber_cache = true;

    /**
     * Additional database indexes for the new
     * "_Versions" table. Used in {@link augmentDatabase()}.
     *
     * @var array $indexes_for_versions_table
     */
    private static $indexes_for_versions_table = [
        'RecordID_Version' => [
            'type' => 'index',
            'columns' => ['RecordID', 'Version'],
        ],
        'RecordID' => [
            'type' => 'index',
            'columns' => ['RecordID'],
        ],
        'Version' => [
            'type' => 'index',
            'columns' => ['Version'],
        ],
        'AuthorID' => [
            'type' => 'index',
            'columns' => ['AuthorID'],
        ],
        'PublisherID' => [
            'type' => 'index',
            'columns' => ['PublisherID'],
        ],
    ];


    /**
     * An array of DataObject extensions that may require versioning for extra tables
     * The array value is a set of suffixes to form these table names, assuming a preceding '_'.
     * E.g. if Extension1 creates a new table 'Class_suffix1'
     * and Extension2 the tables 'Class_suffix2' and 'Class_suffix3':
     *
     *  $versionableExtensions = array(
     *      'Extension1' => 'suffix1',
     *      'Extension2' => array('suffix2', 'suffix3'),
     *  );
     *
     * This can also be manipulated by updating the current loaded config
     *
     * SiteTree:
     *   versionableExtensions:
     *     - Extension1:
     *       - suffix1
     *       - suffix2
     *     - Extension2:
     *       - suffix1
     *       - suffix2
     *
     * or programatically:
     *
     *  Config::modify()->merge($this->owner->class, 'versionableExtensions',
     *  array('Extension1' => 'suffix1', 'Extension2' => array('suffix2', 'suffix3')));
     *
     *
     * Your extension must implement VersionableExtension interface in order to
     * apply custom tables for versioned.
     *
     * @config
     * @var array
     */
    private static $versionableExtensions = [];

    /**
     * Permissions necessary to view records outside of the live stage (e.g. archive / draft stage).
     *
     * @config
     * @var array
     */
    private static $non_live_permissions = ['CMS_ACCESS_LeftAndMain', 'CMS_ACCESS_CMSMain', 'VIEW_DRAFT_CONTENT'];

    /**
     * Reset static configuration variables to their default values.
     */
    public static function reset()
    {
        self::$reading_mode = '';
        Controller::curr()->getRequest()->getSession()->clear('readingMode');
    }

    /**
     * Amend freshly created DataQuery objects with versioned-specific
     * information.
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    public function augmentDataQueryCreation(SQLSelect &$query, DataQuery &$dataQuery)
    {
        $parts = explode('.', Versioned::get_reading_mode());

        if ($parts[0] == 'Archive') {
            $archiveStage = isset($parts[2]) ? $parts[2] : static::DRAFT;
            $dataQuery->setQueryParam('Versioned.mode', 'archive');
            $dataQuery->setQueryParam('Versioned.date', $parts[1]);
            $dataQuery->setQueryParam('Versioned.stage', $archiveStage);
        } elseif ($parts[0] == 'Stage' && $this->hasStages()) {
            $dataQuery->setQueryParam('Versioned.mode', 'stage');
            $dataQuery->setQueryParam('Versioned.stage', $parts[1]);
        }
    }

    /**
     * Construct a new Versioned object.
     *
     * @var string $mode One of "StagedVersioned" or "Versioned".
     */
    public function __construct($mode = self::STAGEDVERSIONED)
    {
        // Handle deprecated behaviour
        if ($mode === 'Stage' && func_num_args() === 1) {
            Deprecation::notice("5.0", "Versioned now takes a mode as a single parameter");
            $mode = static::VERSIONED;
        } elseif (is_array($mode) || func_num_args() > 1) {
            Deprecation::notice("5.0", "Versioned now takes a mode as a single parameter");
            $mode = func_num_args() > 1 || count($mode) > 1
                ? static::STAGEDVERSIONED
                : static::VERSIONED;
        }

        if (!in_array($mode, [static::STAGEDVERSIONED, static::VERSIONED])) {
            throw new InvalidArgumentException("Invalid mode: {$mode}");
        }

        $this->mode = $mode;
    }

    /**
     * Get modified date for the given version
     *
     * @deprecated 4.2..5.0 Use getLastEditedAndStageForVersion instead
     * @param int $version
     * @return string
     */
    protected function getLastEditedForVersion($version)
    {
        Deprecation::notice('5.0', 'Use getLastEditedAndStageForVersion instead');
        $result = $this->getLastEditedAndStageForVersion($version);
        if ($result) {
            return reset($result);
        }
        return null;
    }

    /**
     * Get modified date and stage for the given version
     *
     * @param int $version
     * @return array A list containing 0 => LastEdited, 1 => Stage
     */
    protected function getLastEditedAndStageForVersion($version)
    {
        // Cache key
        $baseTable = $this->baseTable();
        $id = $this->owner->ID;
        $key = "{$baseTable}#{$id}/{$version}";

        // Check cache
        if (isset($this->versionModifiedCache[$key])) {
            return $this->versionModifiedCache[$key];
        }

        // Build query
        $table = "\"{$baseTable}_Versions\"";
        $query = SQLSelect::create(['"LastEdited"', '"WasPublished"'], $table)
            ->addWhere([
                "{$table}.\"RecordID\"" => $id,
                "{$table}.\"Version\"" => $version
            ]);
        $result = $query->execute()->record();
        if (!$result) {
            return null;
        }
        $list = [
            $result['LastEdited'],
            $result['WasPublished'] ? static::LIVE : static::DRAFT,
        ];
        $this->versionModifiedCache[$key] = $list;
        return $list;
    }

    /**
     * Updates query parameters of relations attached to versioned dataobjects
     *
     * @param array $params
     */
    public function updateInheritableQueryParams(&$params)
    {
        // Skip if versioned isn't set
        if (!isset($params['Versioned.mode'])) {
            return;
        }

        // Adjust query based on original selection criterea
        switch ($params['Versioned.mode']) {
            case 'all_versions':
            {
                // Versioned.mode === all_versions doesn't inherit very well, so default to stage
                $params['Versioned.mode'] = 'stage';
                $params['Versioned.stage'] = static::DRAFT;
                break;
            }
            case 'version':
            {
                // If we selected this object from a specific version, we need
                // to find the date this version was published, and ensure
                // inherited queries select from that date.
                $version = $params['Versioned.version'];
                $dateAndStage = $this->getLastEditedAndStageForVersion($version);

                // Filter related objects at the same date as this version
                unset($params['Versioned.version']);
                if ($dateAndStage) {
                    list($date, $stage) = $dateAndStage;
                    $params['Versioned.mode'] = 'archive';
                    $params['Versioned.date'] = $date;
                    $params['Versioned.stage'] = $stage;
                } else {
                    // Fallback to default
                    $params['Versioned.mode'] = 'stage';
                    $params['Versioned.stage'] = static::DRAFT;
                }
                break;
            }
        }
    }

    /**
     * Augment the the SQLSelect that is created by the DataQuery
     *
     * See {@see augmentLazyLoadFields} for lazy-loading applied prior to this.
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     * @throws InvalidArgumentException
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        // Ensure query mode exists
        $versionedMode = $dataQuery->getQueryParam('Versioned.mode');
        if (!$versionedMode) {
            return;
        }
        switch ($versionedMode) {
            case 'stage':
                $this->augmentSQLStage($query, $dataQuery);
                break;
            case 'stage_unique':
                $this->augmentSQLStageUnique($query, $dataQuery);
                break;
            case 'archive':
                $this->augmentSQLVersionedArchive($query, $dataQuery);
                break;
            case 'latest_versions':
                $this->augmentSQLVersionedLatest($query);
                break;
            case 'version':
                $this->augmentSQLVersionedVersion($query, $dataQuery);
                break;
            case 'all_versions':
                $this->augmentSQLVersionedAll($query);
                break;
            default:
                throw new InvalidArgumentException("Bad value for query parameter Versioned.mode: {$versionedMode}");
        }
    }

    /**
     * Reading a specific stage (Stage or Live)
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    protected function augmentSQLStage(SQLSelect $query, DataQuery $dataQuery)
    {
        if (!$this->hasStages()) {
            return;
        }
        $stage = $dataQuery->getQueryParam('Versioned.stage');
        if (!in_array($stage, [static::DRAFT, static::LIVE])) {
            throw new InvalidArgumentException("Invalid stage provided \"{$stage}\"");
        }
        if ($stage === static::DRAFT) {
            return;
        }
        // Rewrite all tables to select from the live version
        foreach ($query->getFrom() as $table => $dummy) {
            if (!$this->isTableVersioned($table)) {
                continue;
            }
            $stageTable = $this->stageTable($table, $stage);
            $query->renameTable($table, $stageTable);
        }
    }

    /**
     * Reading a specific stage, but only return items that aren't in any other stage
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    protected function augmentSQLStageUnique(SQLSelect $query, DataQuery $dataQuery)
    {
        if (!$this->hasStages()) {
            return;
        }
        // Set stage first
        $this->augmentSQLStage($query, $dataQuery);

        // Now exclude any ID from any other stage.
        $stage = $dataQuery->getQueryParam('Versioned.stage');
        $excludingStage = $stage === static::DRAFT ? static::LIVE : static::DRAFT;

        // Note that we double rename to avoid the regular stage rename
        // renaming all subquery references to be Versioned.stage
        $tempName = 'ExclusionarySource_' . $excludingStage;
        $excludingTable = $this->baseTable($excludingStage);
        $baseTable = $this->baseTable($stage);
        $query->addWhere("\"{$baseTable}\".\"ID\" NOT IN (SELECT \"ID\" FROM \"{$tempName}\")");
        $query->renameTable($tempName, $excludingTable);
    }

    /**
     * Augment SQL to select from `_Versioned` table instead.
     *
     * @param SQLSelect $query
     */
    protected function augmentSQLVersioned(SQLSelect $query)
    {
        $baseTable = $this->baseTable();
        foreach ($query->getFrom() as $alias => $join) {
            if (!$this->isTableVersioned($alias)) {
                continue;
            }

            if ($alias != $baseTable) {
                // Make sure join includes version as well
                $query->setJoinFilter(
                    $alias,
                    "\"{$alias}_Versions\".\"RecordID\" = \"{$baseTable}_Versions\".\"RecordID\""
                    . " AND \"{$alias}_Versions\".\"Version\" = \"{$baseTable}_Versions\".\"Version\""
                );
            }

            // Rewrite all usages of `Table` to `Table_Versions`
            $query->renameTable($alias, $alias . '_Versions');
            // However, add an alias back to the base table in case this must later be joined.
            // See ApplyVersionFilters for example which joins _Versioned back onto draft table.
            $query->renameTable($alias . '_Draft', $alias);
        }

        // Add all <basetable>_Versions columns
        foreach (Config::inst()->get(static::class, 'db_for_versions_table') as $name => $type) {
            $query->selectField(sprintf('"%s_Versions"."%s"', $baseTable, $name), $name);
        }

        // Alias the record ID as the row ID, and ensure ID filters are aliased correctly
        $query->selectField("\"{$baseTable}_Versions\".\"RecordID\"", "ID");
        $query->replaceText("\"{$baseTable}_Versions\".\"ID\"", "\"{$baseTable}_Versions\".\"RecordID\"");

        // However, if doing count, undo rewrite of "ID" column
        $query->replaceText(
            "count(DISTINCT \"{$baseTable}_Versions\".\"RecordID\")",
            "count(DISTINCT \"{$baseTable}_Versions\".\"ID\")"
        );

        // Filter deleted versions, which are all unqueryable
        $query->addWhere(["\"{$baseTable}_Versions\".\"WasDeleted\"" => 0]);
    }

    /**
     * Filter the versioned history by a specific date and archive stage
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    protected function augmentSQLVersionedArchive(SQLSelect $query, DataQuery $dataQuery)
    {
        $baseTable = $this->baseTable();
        $date = $dataQuery->getQueryParam('Versioned.date');
        if (!$date) {
            throw new InvalidArgumentException("Invalid archive date");
        }

        // Query against _Versioned table first
        $this->augmentSQLVersioned($query);

        // Validate stage
        $stage = $dataQuery->getQueryParam('Versioned.stage');
        if (!in_array($stage, [static::DRAFT, static::LIVE])) {
            throw new InvalidArgumentException("Invalid stage provided \"{$stage}\"");
        }

        // Filter on appropriate stage column in addition to date
        $stageColumn = $stage === static::LIVE
            ? 'WasPublished'
            : 'WasDraft';

        // Join on latest version filtered by date
        $query->addInnerJoin(
            <<<SQL
            (
            SELECT "{$baseTable}_Versions"."RecordID",
                MAX("{$baseTable}_Versions"."Version") AS "LatestVersion"
            FROM "{$baseTable}_Versions"
            WHERE "{$baseTable}_Versions"."LastEdited" <= ?
                AND "{$baseTable}_Versions"."{$stageColumn}" = 1
            GROUP BY "{$baseTable}_Versions"."RecordID"
            )                                
SQL
            ,
            <<<SQL
            "{$baseTable}_Versions_Latest"."RecordID" = "{$baseTable}_Versions"."RecordID"
            AND "{$baseTable}_Versions_Latest"."LatestVersion" = "{$baseTable}_Versions"."Version"
SQL
            ,
            "{$baseTable}_Versions_Latest",
            20,
            [$date]
        );
    }

    /**
     * Return latest version instances, regardless of whether they are on a particular stage.
     * This provides "show all, including deleted" functonality.
     *
     * Note: latest_version ignores deleted versions, and will select the latest non-deleted
     * version.
     *
     * @param SQLSelect $query
     */
    protected function augmentSQLVersionedLatest(SQLSelect $query)
    {
        // Query against _Versioned table first
        $this->augmentSQLVersioned($query);

        // Join and select only latest version
        $baseTable = $this->baseTable();
        $query->addInnerJoin(
            <<<SQL
            (
            SELECT "{$baseTable}_Versions"."RecordID",
                MAX("{$baseTable}_Versions"."Version") AS "LatestVersion"
            FROM "{$baseTable}_Versions"
            WHERE "{$baseTable}_Versions"."WasDeleted" = 0
            GROUP BY "{$baseTable}_Versions"."RecordID"
            )                                
SQL
            ,
            <<<SQL
            "{$baseTable}_Versions_Latest"."RecordID" = "{$baseTable}_Versions"."RecordID"
            AND "{$baseTable}_Versions_Latest"."LatestVersion" = "{$baseTable}_Versions"."Version"
SQL
            ,
            "{$baseTable}_Versions_Latest"
        );
    }

    /**
     * If selecting a specific version, filter it here
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    protected function augmentSQLVersionedVersion(SQLSelect $query, DataQuery $dataQuery)
    {
        $version = $dataQuery->getQueryParam('Versioned.version');
        if (!$version) {
            throw new InvalidArgumentException("Invalid version");
        }

        // Query against _Versioned table first
        $this->augmentSQLVersioned($query);

        // Add filter on version field
        $baseTable = $this->baseTable();
        $query->addWhere([
            "\"{$baseTable}_Versions\".\"Version\"" => $version,
        ]);
    }

    /**
     * If all versions are requested, ensure that records are sorted by this field
     *
     * @param SQLSelect $query
     */
    protected function augmentSQLVersionedAll(SQLSelect $query)
    {
        // Query against _Versioned table first
        $this->augmentSQLVersioned($query);

        $baseTable = $this->baseTable();
        $query->addOrderBy("\"{$baseTable}_Versions\".\"Version\"");
    }

    /**
     * Determine if the given versioned table is a part of the sub-tree of the current dataobject
     * This helps prevent rewriting of other tables that get joined in, in particular, many_many tables
     *
     * @param string $table
     * @return bool True if this table should be versioned
     */
    protected function isTableVersioned($table)
    {
        $schema = DataObject::getSchema();
        $tableClass = $schema->tableClass($table);
        if (empty($tableClass)) {
            return false;
        }

        // Check that this class belongs to the same tree
        $baseClass = $schema->baseDataClass($this->owner);
        if (!is_a($tableClass, $baseClass, true)) {
            return false;
        }

        // Check that this isn't a derived table
        // (e.g. _Live, or a many_many table)
        $mainTable = $schema->tableName($tableClass);
        if ($mainTable !== $table) {
            return false;
        }

        return true;
    }

    /**
     * For lazy loaded fields requiring extra sql manipulation, ie versioning.
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     * @param DataObject $dataObject
     */
    public function augmentLoadLazyFields(SQLSelect &$query, DataQuery &$dataQuery = null, $dataObject)
    {
        // The VersionedMode local variable ensures that this decorator only applies to
        // queries that have originated from the Versioned object, and have the Versioned
        // metadata set on the query object. This prevents regular queries from
        // accidentally querying the *_Versions tables.
        $versionedMode = $dataObject->getSourceQueryParam('Versioned.mode');
        $modesToAllowVersioning = ['all_versions', 'latest_versions', 'archive', 'version'];
        if (!empty($dataObject->Version) &&
            (!empty($versionedMode) && in_array($versionedMode, $modesToAllowVersioning))
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

    public function augmentDatabase()
    {
        $owner = $this->owner;
        $class = get_class($owner);
        $schema = $owner->getSchema();
        $baseTable = $this->baseTable();
        $classTable = $schema->tableName($owner);

        $isRootClass = $class === $owner->baseClass();

        // Build a list of suffixes whose tables need versioning
        $allSuffixes = [];
        $versionableExtensions = $owner->config()->get('versionableExtensions');
        if (count($versionableExtensions)) {
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
                    $liveTable = $this->stageTable($suffixTable, static::LIVE);
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
                    $liveTable = $this->stageTable($suffixTable, static::LIVE);
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
        // Skip if child table doesn't exist
        if (!DB::get_schema()->hasTable($childTable)) {
            return;
        }
        // Skip if tables are the same
        if ($childTable === $baseTable) {
            return;
        }

        // Select all orphaned version records
        $orphanedQuery = SQLSelect::create()
            ->selectField("\"{$childTable}\".\"ID\"")
            ->setFrom("\"{$childTable}\"");

        // If we have a parent table limit orphaned records
        // to only those that exist in this
        if (DB::get_schema()->hasTable($baseTable)) {
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
        if (!$isDelete) {
            $data = DB::prepared_query("SELECT * FROM \"{$table}\" WHERE \"ID\" = ?", [$recordID])->record();
            if ($data) {
                $fields = $schema->databaseFields($class, false);
                if (is_array($fields)) {
                    $data = array_intersect_key($data, $fields);

                    foreach ($data as $k => $v) {
                        // If the value is not set at all in the manipulation currently, use the existing value from the database
                        if (!array_key_exists($k, $newManipulation['fields'])) {
                            $newManipulation['fields'][$k] = $v;
                        }
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
            $wasPublished = (int)in_array(static::LIVE, (array)$stages);
            $wasDraft = (int)in_array(static::DRAFT, (array)$stages);
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
    }

    public function augmentWrite(&$manipulation)
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
        $tables = array_keys($manipulation);
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
                user_error("Couldn't find ID in " . var_export($manipulation[$table], true), E_USER_ERROR);
            }

            if ($version < 0 || $this->getNextWriteWithoutVersion()) {
                // Putting a Version of -1 is a signal to leave the version table alone, despite their being no version
                unset($manipulation[$table]['fields']['Version']);
            } else {
                // All writes are to draft, only live affect both
                $stages = static::get_stage() === static::LIVE
                    ? [self::DRAFT, self::LIVE]
                    : [self::DRAFT];
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
            if ($this->hasStages() && static::get_stage() === static::LIVE) {
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
            $owner->Version = str_replace("'", "", $thisVersion);
        }
    }

    /**
     * Perform a write without affecting the version table.
     * On objects without versioning.
     *
     * @return int The ID of the record
     */
    public function writeWithoutVersion()
    {
        $this->setNextWriteWithoutVersion(true);

        return $this->owner->write();
    }

    /**
     *
     */
    public function onAfterWrite()
    {
        $this->setNextWriteWithoutVersion(false);
    }

    /**
     * Check if next write is without version
     *
     * @return bool
     */
    public function getNextWriteWithoutVersion()
    {
        return $this->owner->getField(self::NEXT_WRITE_WITHOUT_VERSIONED);
    }

    /**
     * Set if next write should be without version or not
     *
     * @param bool $flag
     * @return DataObject owner
     */
    public function setNextWriteWithoutVersion($flag)
    {
        return $this->owner->setField(self::NEXT_WRITE_WITHOUT_VERSIONED, $flag);
    }

    /**
     * Check if delete() should write _Version rows or not
     *
     * @return bool
     */
    public function getDeleteWritesVersion()
    {
        return !$this->owner->getField(self::DELETE_WRITES_VERSION_DISABLED);
    }

    /**
     * Set if delete() should write _Version rows
     *
     * @param bool $flag
     * @return DataObject owner
     */
    public function setDeleteWritesVersion($flag)
    {
        return $this->owner->setField(self::DELETE_WRITES_VERSION_DISABLED, !$flag);
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
     * If a write was skipped, then we need to ensure that we don't leave a
     * migrateVersion() value lying around for the next write.
     */
    public function onAfterSkippedWrite()
    {
        $this->setMigratingVersion(null);
    }

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
        // Skip if invoked by extendedCan()
        if (func_num_args() > 4) {
            return null;
        }

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

    /**
     * Check if the current user can delete this record from live
     *
     * @param null $member
     * @return mixed
     */
    public function canUnpublish($member = null)
    {
        // Skip if invoked by extendedCan()
        if (func_num_args() > 4) {
            return null;
        }

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

    /**
     * Check if the current user is allowed to archive this record.
     * If extended, ensure that both canDelete and canUnpublish are extended also
     *
     * @param Member $member
     * @return bool
     */
    public function canArchive($member = null)
    {
        // Skip if invoked by extendedCan()
        if (func_num_args() > 4) {
            return null;
        }

        if (!$member) {
            $member = Security::getCurrentUser();
        }

        // Standard mechanism for accepting permission changes from extensions
        $owner = $this->owner;
        $extended = $owner->extendedCan('canArchive', $member);
        if ($extended !== null) {
            return $extended;
        }

        // Admin permissions allow
        if (Permission::checkMember($member, "ADMIN")) {
            return true;
        }

        // Check if this record can be deleted from stage
        if (!$owner->canDelete($member)) {
            return false;
        }

        // Check if we can delete from live
        if (!$owner->canUnpublish($member)) {
            return false;
        }

        return true;
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

        // Skip if invoked by extendedCan()
        if (func_num_args() > 4) {
            return null;
        }

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

    /**
     * Extend permissions to include additional security for objects that are not published to live.
     *
     * @param Member $member
     * @return bool|null
     */
    public function canView($member = null)
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
        $mode = $owner->getSourceQueryParam("Versioned.mode") ?: 'stage';
        $stage = $owner->getSourceQueryParam("Versioned.stage") ?: Versioned::get_stage();
        if ($mode === 'stage' && $stage === static::LIVE) {
            return true;
        }

        // Bypass if site is unsecured
        if (Versioned::get_stage() === $stage) {
            return true;
        }

        // Bypass if record doesn't have a live stage
        if (!$this->hasStages()) {
            return true;
        }

        // If we weren't definitely loaded from live, and we can't view non-live content, we need to
        // check to make sure this version is the live version and so can be viewed
        $latestVersion = Versioned::get_versionnumber_by_stage(get_class($owner), static::LIVE, $owner->ID);
        if ($latestVersion == $owner->Version) {
            // Even if this is loaded from a non-live stage, this is the live version
            return true;
        }

        // If stages are synchronised treat this as the live stage
        if ($mode === 'stage' && !$this->stagesDiffer()) {
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
    public function canViewStage($stage = 'Live', $member = null)
    {
        $oldMode = Versioned::get_reading_mode();
        Versioned::set_stage($stage);

        $owner = $this->owner;
        $versionFromStage = DataObject::get(get_class($owner))->byID($owner->ID);

        Versioned::set_reading_mode($oldMode);
        return $versionFromStage ? $versionFromStage->canView($member) : false;
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
        $versionableExtensions = $owner->config()->get('versionableExtensions');

        if (count($versionableExtensions)) {
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
        $draftVersion = static::get_versionnumber_by_stage($this->owner, Versioned::DRAFT, $id);
        $liveVersion = static::get_versionnumber_by_stage($this->owner, Versioned::LIVE, $id);
        return $draftVersion === $liveVersion;
    }

    /**
     * @deprecated 4.0..5.0
     */
    public function doPublish()
    {
        Deprecation::notice('5.0', 'Use publishRecursive instead');
        return $this->owner->publishRecursive();
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
            $original = self::get_by_stage($owner->baseClass(), self::LIVE)
                ->byID($owner->ID);
        }

        // Publish it
        $owner->invokeWithExtensions('onBeforePublish', $original);
        $owner->writeToStage(static::LIVE);
        $owner->invokeWithExtensions('onAfterPublish', $original);
        return true;
    }

    /**
     * Removes the record from both live and stage
     *
     * User code should call {@see canArchive()} prior to invoking this method.
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
            $owner->deleteFromStage(static::DRAFT);
        });
        // Create deleted version in both stages
        $this->createDeletedVersion([
            static::LIVE,
            static::DRAFT,
        ]);
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

        $origReadingMode = static::get_reading_mode();
        try {
            static::set_stage(static::LIVE);

            // This way our ID won't be unset
            $clone = clone $owner;
            $clone->delete();
        } finally {
            static::set_reading_mode($origReadingMode);
        }

        $owner->invokeWithExtensions('onAfterUnpublish');
        return true;
    }

    public function onAfterDelete()
    {
        // Create deleted record for current stage
        $this->createDeletedVersion(static::get_stage());
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
        /** @var Versioned|RecursivePublishable|DataObject $liveRecord */
        $liveRecord = static::get_by_stage(get_class($this->owner), Versioned::LIVE)->byID($this->owner->ID);
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
        $owner->copyVersionToStage(static::LIVE, static::DRAFT);
        $owner->invokeWithExtensions('onAfterRevertToLive');
        return true;
    }

    /**
     * Trigger revert of all owned objects to stage
     */
    public function onAfterRevertToLive()
    {
        $owner = $this->owner;
        /** @var Versioned|RecursivePublishable|DataObject $liveOwner */
        $liveOwner = static::get_by_stage(get_class($owner), static::LIVE)
            ->byID($owner->ID);

        // Revert any owned objects from the live stage only
        foreach ($liveOwner->findOwned(false) as $object) {
            // Skip unversioned owned objects
            if (!$object->hasExtension(Versioned::class)) {
                continue;
            }
            /** @var Versioned|DataObject $object */
            $object->doRevertToLive();
        }

        // Unlink any objects disowned as a result of this action
        // I.e. objects which aren't owned anymore by this record, but are by the old draft record
        $owner->unlinkDisownedObjects(Versioned::LIVE, Versioned::DRAFT);
    }

    /**
     * @deprecated 4.0..5.0
     */
    public function publish($fromStage, $toStage, $createNewVersion = true)
    {
        Deprecation::notice('5.0', 'Use copyVersionToStage instead');
        $this->owner->copyVersionToStage($fromStage, $toStage, true);
    }

    /**
     * Move a database record from one stage to the other.
     *
     * @param int|string $fromStage Place to copy from.  Can be either a stage name or a version number.
     * @param string $toStage Place to copy to.  Must be a stage name.
     * @param bool $createNewVersion [DEPRECATED] This parameter is ignored, as copying to stage should always
     * create a new version.
     */
    public function copyVersionToStage($fromStage, $toStage, $createNewVersion = true)
    {
        // Disallow $createNewVersion = false
        if (!$createNewVersion) {
            Deprecation::notice('5.0', 'copyVersionToStage no longer allows $createNewVersion to be false');
            $createNewVersion = true;
        }
        $owner = $this->owner;
        $owner->invokeWithExtensions('onBeforeVersionedPublish', $fromStage, $toStage, $createNewVersion);

        $baseClass = $owner->baseClass();
        /** @var Versioned|DataObject $from */
        if (is_numeric($fromStage)) {
            $from = Versioned::get_version($baseClass, $owner->ID, $fromStage);
        } else {
            $from = Versioned::get_by_stage($baseClass, $fromStage)->byID($owner->ID);
        }
        if (!$from) {
            throw new InvalidArgumentException("Can't find {$baseClass}#{$owner->ID} in stage {$fromStage}");
        }

        $from->writeToStage($toStage);
        $from->destroy();
        $owner->invokeWithExtensions('onAfterVersionedPublish', $fromStage, $toStage, $createNewVersion);
    }

    /**
     * Get version migrated to
     *
     * @return int|null
     */
    public function getMigratingVersion()
    {
        return $this->owner->getField(self::MIGRATING_VERSION);
    }

    /**
     * @deprecated 4.0...5.0
     * @param string $version The version.
     */
    public function migrateVersion($version)
    {
        Deprecation::notice('5.0', 'use setMigratingVersion instead');
        $this->setMigratingVersion($version);
    }

    /**
     * Set the migrating version.
     *
     * @param string $version The version.
     * @return DataObject Owner
     */
    public function setMigratingVersion($version)
    {
        return $this->owner->setField(self::MIGRATING_VERSION, $version);
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
        if (func_num_args() > 0) {
            Deprecation::notice('5.0', 'Versioned only has two stages and stagesDiffer no longer requires parameters');
        }
        $id = $this->owner->ID ?: $this->owner->OldID;
        if (!$id || !$this->hasStages()) {
            return false;
        }

        $draftVersion = static::get_versionnumber_by_stage($this->owner, Versioned::DRAFT, $id);
        $liveVersion = static::get_versionnumber_by_stage($this->owner, Versioned::LIVE, $id);
        return $draftVersion !== $liveVersion;
    }

    /**
     * @param string $filter
     * @param string $sort
     * @param string $limit
     * @param string $join Deprecated, use leftJoin($table, $joinClause) instead
     * @param string $having
     * @return ArrayList
     */
    public function Versions($filter = "", $sort = "", $limit = "", $join = "", $having = "")
    {
        return $this->allVersions($filter, $sort, $limit, $join, $having);
    }

    /**
     * Return a list of all the versions available.
     *
     * @param  string $filter
     * @param  string $sort
     * @param  string $limit
     * @param  string $join @deprecated use leftJoin($table, $joinClause) instead
     * @param  string $having @deprecated
     * @return ArrayList
     */
    public function allVersions($filter = "", $sort = "", $limit = "", $join = "", $having = "")
    {
        // Make sure the table names are not postfixed (e.g. _Live)
        $oldMode = static::get_reading_mode();
        static::set_stage(static::DRAFT);

        $owner = $this->owner;
        $list = DataObject::get(DataObject::getSchema()->baseDataClass($owner), $filter, $sort, $join, $limit);
        if ($having) {
            // @todo - This method doesn't exist on DataList
            $list->having($having);
        }

        $query = $list->dataQuery()->query();

        $baseTable = null;
        foreach ($query->getFrom() as $table => $tableJoin) {
            if (is_string($tableJoin) && $tableJoin[0] == '"') {
                $baseTable = str_replace('"', '', $tableJoin);
            } elseif (is_string($tableJoin) && substr($tableJoin, 0, 5) != 'INNER') {
                $query->setFrom([
                    $table => "LEFT JOIN \"$table\" ON \"$table\".\"RecordID\"=\"{$baseTable}_Versions\".\"RecordID\""
                        . " AND \"$table\".\"Version\" = \"{$baseTable}_Versions\".\"Version\""
                ]);
            }
            $query->renameTable($table, $table . '_Versions');
        }

        // Add all <basetable>_Versions columns
        foreach (Config::inst()->get(static::class, 'db_for_versions_table') as $name => $type) {
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
        $fromRecord = Versioned::get_version(get_class($owner), $owner->ID, $from);
        $toRecord = Versioned::get_version(get_class($owner), $owner->ID, $to);

        $diff = new DataDifferencer($fromRecord, $toRecord);

        return $diff->diffedData();
    }

    /**
     * Return the base table - the class that directly extends DataObject.
     *
     * Protected so it doesn't conflict with DataObject::baseTable()
     *
     * @param string $stage
     * @return string
     */
    protected function baseTable($stage = null)
    {
        $baseTable = $this->owner->baseTable();
        return $this->stageTable($baseTable, $stage);
    }

    /**
     * Given a table and stage determine the table name.
     *
     * Note: Stages this asset does not exist in will default to the draft table.
     *
     * @param string $table Main table
     * @param string $stage
     * @return string Staged table name
     */
    public function stageTable($table, $stage)
    {
        if ($this->hasStages() && $stage === static::LIVE) {
            return "{$table}_{$stage}";
        }
        return $table;
    }

    //-----------------------------------------------------------------------------------------------//


    /**
     * Determine if the current user is able to set the given site stage / archive
     *
     * @param HTTPRequest $request
     * @return bool
     */
    public static function can_choose_site_stage($request)
    {
        // Request is allowed if stage isn't being modified
        if ((!$request->getVar('stage') || $request->getVar('stage') === static::LIVE)
            && !$request->getVar('archiveDate')
        ) {
            return true;
        }

        // Check permissions with member ID in session.
        $member = Security::getCurrentUser();
        $permissions = Config::inst()->get(get_called_class(), 'non_live_permissions');
        return $member && Permission::checkMember($member, $permissions);
    }

    /**
     * Choose the stage the site is currently on.
     *
     * If $_GET['stage'] is set, then it will use that stage, and store it in
     * the session.
     *
     * if $_GET['archiveDate'] is set, it will use that date, and store it in
     * the session.
     *
     * If neither of these are set, it checks the session, otherwise the stage
     * is set to 'Live'.
     * @param HTTPRequest $request
     */
    public static function choose_site_stage(HTTPRequest $request)
    {
        // Check any pre-existing session mode
        $mode = static::DEFAULT_MODE;

        // Check reading mode
        $getStage = $request->getVar('stage');
        if ($getStage) {
            if (strcasecmp($getStage, static::DRAFT) === 0) {
                $stage = static::DRAFT;
            } else {
                $stage = static::LIVE;
            }
            $mode = 'Stage.' . $stage;
        }

        // Check archived date
        $getArchived = $request->getVar('archiveDate');
        if ($getArchived && strtotime($getArchived)) {
            $mode = 'Archive.' . $getArchived;
            $stageArchived = $request->getVar('stage');
            if ($stageArchived) {
                $mode .= '.' . $stageArchived;
            }
        }

        // Save reading mode
        Versioned::set_reading_mode($mode);

        if (!headers_sent() && !Director::is_cli()) {
            if (Versioned::get_stage() === static::LIVE) {
                // clear the cookie if it's set
                if (Cookie::get('bypassStaticCache')) {
                    Cookie::force_expiry('bypassStaticCache', null, null, false, true /* httponly */);
                }
            } else {
                // set the cookie if it's cleared
                if (!Cookie::get('bypassStaticCache')) {
                    Cookie::set('bypassStaticCache', '1', 0, null, null, false, true /* httponly */);
                }
            }
        }
    }

    /**
     * Set the current reading mode.
     *
     * @param string $mode
     */
    public static function set_reading_mode($mode)
    {
        self::$reading_mode = $mode;
    }

    /**
     * Get the current reading mode.
     *
     * @return string
     */
    public static function get_reading_mode()
    {
        return self::$reading_mode;
    }

    /**
     * Get the current reading stage.
     *
     * @return string
     */
    public static function get_stage()
    {
        $parts = explode('.', Versioned::get_reading_mode());

        if ($parts[0] == 'Stage') {
            return $parts[1];
        }
        return null;
    }

    /**
     * Get the current archive date.
     *
     * @return string
     */
    public static function current_archived_date()
    {
        $parts = explode('.', Versioned::get_reading_mode());
        if ($parts[0] == 'Archive') {
            return $parts[1];
        }
        return null;
    }

    /**
     * Get the current archive stage.
     *
     * @return string
     */
    public static function current_archived_stage()
    {
        $parts = explode('.', Versioned::get_reading_mode());
        if (sizeof($parts) === 3 && $parts[0] == 'Archive') {
            return $parts[2];
        }
        return static::DRAFT;
    }

    /**
     * Set the reading stage.
     *
     * @param string $stage New reading stage.
     * @throws InvalidArgumentException
     */
    public static function set_stage($stage)
    {
        if (!in_array($stage, [static::LIVE, static::DRAFT])) {
            throw new \InvalidArgumentException("Invalid stage name \"{$stage}\"");
        }
        static::set_reading_mode('Stage.' . $stage);
    }

    /**
     * Set the reading archive date.
     *
     * @param string $date New reading archived date.
     */
    public static function reading_archived_date($date)
    {
        Versioned::set_reading_mode('Archive.' . $date);
    }


    /**
     * Get a singleton instance of a class in the given stage.
     *
     * @param string $class The name of the class.
     * @param string $stage The name of the stage.
     * @param string $filter A filter to be inserted into the WHERE clause.
     * @param boolean $cache Use caching.
     * @param string $sort A sort expression to be inserted into the ORDER BY clause.
     *
     * @return DataObject
     */
    public static function get_one_by_stage($class, $stage, $filter = '', $cache = true, $sort = '')
    {
        try {
            $origMode = Versioned::get_reading_mode();
            Versioned::set_stage($stage);
            return DataObject::get_one($class, $filter, $cache, $sort);
        } finally {
            Versioned::set_reading_mode($origMode);
        }
    }

    /**
     * Gets the current version number of a specific record.
     *
     * @param string $class Class to search
     * @param string $stage Stage name
     * @param int $id ID of the record
     * @param bool $cache Set to true to turn on cache
     * @return int|null Return the version number, or null if not on this stage
     */
    public static function get_versionnumber_by_stage($class, $stage, $id, $cache = true)
    {
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        $stageTable = DataObject::getSchema()->tableName($baseClass);
        if ($stage === static::LIVE) {
            $stageTable .= "_{$stage}";
        }

        // cached call
        if ($cache && isset(self::$cache_versionnumber[$baseClass][$stage][$id])) {
            return self::$cache_versionnumber[$baseClass][$stage][$id] ?: null;
        }

        // get version as performance-optimized SQL query (gets called for each record in the sitetree)
        $version = DB::prepared_query(
            "SELECT \"Version\" FROM \"$stageTable\" WHERE \"ID\" = ?",
            [$id]
        )->value();

        // cache value (if required)
        if ($cache) {
            if (!isset(self::$cache_versionnumber[$baseClass])) {
                self::$cache_versionnumber[$baseClass] = [];
            }

            if (!isset(self::$cache_versionnumber[$baseClass][$stage])) {
                self::$cache_versionnumber[$baseClass][$stage] = [];
            }

            // Internally store nulls as 0
            self::$cache_versionnumber[$baseClass][$stage][$id] = $version ?: 0;
        }

        return $version ?: null;
    }

    /**
     * Pre-populate the cache for Versioned::get_versionnumber_by_stage() for
     * a list of record IDs, for more efficient database querying.  If $idList
     * is null, then every record will be pre-cached.
     *
     * @param string $class
     * @param string $stage
     * @param array $idList
     */
    public static function prepopulate_versionnumber_cache($class, $stage, $idList = null)
    {
        if (!Config::inst()->get(static::class, 'prepopulate_versionnumber_cache')) {
            return;
        }
        $filter = "";
        $parameters = [];
        if ($idList) {
            // Validate the ID list
            foreach ($idList as $id) {
                if (!is_numeric($id)) {
                    user_error(
                        "Bad ID passed to Versioned::prepopulate_versionnumber_cache() in \$idList: " . $id,
                        E_USER_ERROR
                    );
                }
            }
            $filter = 'WHERE "ID" IN (' . DB::placeholders($idList) . ')';
            $parameters = $idList;
        }

        /** @var Versioned|DataObject $singleton */
        $singleton = DataObject::singleton($class);
        $baseClass = $singleton->baseClass();
        $baseTable = $singleton->baseTable();
        $stageTable = $singleton->stageTable($baseTable, $stage);

        $versions = DB::prepared_query("SELECT \"ID\", \"Version\" FROM \"$stageTable\" $filter", $parameters)->map();

        foreach ($versions as $id => $version) {
            self::$cache_versionnumber[$baseClass][$stage][$id] = $version;
        }
    }

    /**
     * Get a set of class instances by the given stage.
     *
     * @param string $class The name of the class.
     * @param string $stage The name of the stage.
     * @param string $filter A filter to be inserted into the WHERE clause.
     * @param string $sort A sort expression to be inserted into the ORDER BY clause.
     * @param string $join Deprecated, use leftJoin($table, $joinClause) instead
     * @param int $limit A limit on the number of records returned from the database.
     * @param string $containerClass The container class for the result set (default is DataList)
     *
     * @return DataList A modified DataList designated to the specified stage
     */
    public static function get_by_stage(
        $class,
        $stage,
        $filter = '',
        $sort = '',
        $join = '',
        $limit = null,
        $containerClass = DataList::class
    ) {
        $result = DataObject::get($class, $filter, $sort, $join, $limit, $containerClass);
        return $result->setDataQueryParam([
            'Versioned.mode' => 'stage',
            'Versioned.stage' => $stage
        ]);
    }

    /**
     * Delete this record from the given stage
     *
     * @param string $stage
     */
    public function deleteFromStage($stage)
    {
        $oldMode = Versioned::get_reading_mode();
        try {
            Versioned::set_stage($stage);
            $owner = $this->owner;
            $clone = clone $owner;
            $clone->delete();
        } finally {
            Versioned::set_reading_mode($oldMode);
        }
        // Fix the version number cache (in case you go delete from stage and then check ExistsOnLive)
        $baseClass = $owner->baseClass();
        self::$cache_versionnumber[$baseClass][$stage][$owner->ID] = null;
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
        $owner = $this->owner;
        $oldMode = Versioned::get_reading_mode();
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
            $owner->invokeWithExtensions('onBeforeWriteToStage', $toStage, $forceInsert);
            return $owner->write(false, $forceInsert);
        } finally {
            // Revert global state
            $owner->invokeWithExtensions('onAfterWriteToStage', $toStage, $forceInsert);
            $owner->setSourceQueryParams($oldParams);
            Versioned::set_reading_mode($oldMode);
        }
    }

    /**
     * Roll the draft version of this record to match the published record.
     * Caution: Doesn't overwrite the object properties with the rolled back version.
     *
     * {@see doRevertToLive()} to reollback to live
     *
     * @param int $version Version number
     */
    public function doRollbackTo($version)
    {
        $owner = $this->owner;
        $owner->extend('onBeforeRollback', $version);
        $owner->copyVersionToStage($version, static::DRAFT);
        $owner->extend('onAfterRollback', $version);
    }

    public function onAfterRollback($version)
    {
        // Find record at this version
        $baseClass = DataObject::getSchema()->baseDataClass($this->owner);
        /** @var Versioned|RecursivePublishable|DataObject $recordVersion */
        $recordVersion = static::get_version($baseClass, $this->owner->ID, $version);

        // Note that unlike other publishing actions, rollback is NOT recursive;
        // The owner collects all objects and writes them back using writeToStage();
        foreach ($recordVersion->findOwned() as $object) {
            // Skip unversioned owned objects
            if (!$object->hasExtension(Versioned::class)) {
                continue;
            }
            /** @var Versioned|DataObject $object */
            $object->writeToStage(static::DRAFT);
        }
    }

    /**
     * Return the latest version of the given record.
     *
     * @param string $class
     * @param int $id
     * @return DataObject
     */
    public static function get_latest_version($class, $id)
    {
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        $list = DataList::create($baseClass)
            ->setDataQueryParam("Versioned.mode", "latest_versions");

        return $list->byID($id);
    }

    /**
     * Returns whether the current record is the latest one.
     *
     * @todo Performance - could do this directly via SQL.
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

        /** @var Versioned|DataObject $version */
        $version = static::get_latest_version(get_class($owner), $owner->ID);
        return ($version->Version == $owner->Version);
    }

    /**
     * Check if this record exists on live
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
            return true;
        }

        $liveVersion = static::get_versionnumber_by_stage($this->owner, Versioned::LIVE, $id);
        return (bool)$liveVersion;
    }

    /**
     * Check if page doesn't exist on any stage, but used to be
     *
     * @return bool
     */
    public function isArchived()
    {
        $id = $this->owner->ID ?: $this->owner->OldID;
        return $id && !$this->isOnDraft() && !$this->isPublished();
    }

    /**
     * Check if this record exists on the draft stage
     *
     * @return bool
     */
    public function isOnDraft()
    {
        $id = $this->owner->ID ?: $this->owner->OldID;
        if (!$id) {
            return false;
        }
        if (!$this->hasStages()) {
            return true;
        }

        $draftVersion = static::get_versionnumber_by_stage($this->owner, Versioned::DRAFT, $id);
        return (bool)$draftVersion;
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

    /**
     * Return the equivalent of a DataList::create() call, querying the latest
     * version of each record stored in the (class)_Versions tables.
     *
     * In particular, this will query deleted records as well as active ones.
     *
     * @param string $class
     * @param string $filter
     * @param string $sort
     * @return DataList
     */
    public static function get_including_deleted($class, $filter = "", $sort = "")
    {
        $list = DataList::create($class)
            ->where($filter)
            ->sort($sort)
            ->setDataQueryParam("Versioned.mode", "latest_versions");

        return $list;
    }

    /**
     * Return the specific version of the given id.
     *
     * Caution: The record is retrieved as a DataObject, but saving back
     * modifications via write() will create a new version, rather than
     * modifying the existing one.
     *
     * @param string $class
     * @param int $id
     * @param int $version
     *
     * @return DataObject
     */
    public static function get_version($class, $id, $version)
    {
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        $list = DataList::create($baseClass)
            ->setDataQueryParam([
                "Versioned.mode" => 'version',
                "Versioned.version" => $version
            ]);

        return $list->byID($id);
    }

    /**
     * Return a list of all versions for a given id.
     *
     * @param string $class
     * @param int $id
     *
     * @return DataList
     */
    public static function get_all_versions($class, $id)
    {
        $list = DataList::create($class)
            ->filter('ID', $id)
            ->setDataQueryParam('Versioned.mode', 'all_versions');

        return $list;
    }

    /**
     * @param array $labels
     */
    public function updateFieldLabels(&$labels)
    {
        $labels['Versions'] = _t(__CLASS__ . '.has_many_Versions', 'Versions', 'Past Versions of this record');
    }

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
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
    public function onBeforeDuplicate($source, $doWrite)
    {
        $this->owner->Version = 0;
    }

    public function flushCache()
    {
        self::$cache_versionnumber = [];
        $this->versionModifiedCache = [];
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific.
     *
     * @return string
     */
    public function cacheKeyComponent()
    {
        return 'versionedmode-' . static::get_reading_mode();
    }

    /**
     * Returns an array of possible stages.
     *
     * @return array
     */
    public function getVersionedStages()
    {
        if ($this->hasStages()) {
            return [static::DRAFT, static::LIVE];
        } else {
            return [static::DRAFT];
        }
    }

    public static function get_template_global_variables()
    {
        return [
            'CurrentReadingMode' => 'get_reading_mode'
        ];
    }

    /**
     * Check if this object has stages
     *
     * @return bool True if this object is staged
     */
    public function hasStages()
    {
        return $this->mode === static::STAGEDVERSIONED;
    }
}
