<?php

namespace SilverStripe\Versioned\Mode\Traits;

use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\DataQuery;
use SilverStripe\Versioned\Mode\Versioned;
use SilverStripe\Versioned\Mode\ReadingMode;
use InvalidArgumentException;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;

trait VersionedAugmentSqlTrait
{
    /**
     * Amend freshly created DataQuery objects with versioned-specific
     * information.
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    protected function augmentDataQueryCreation(SQLSelect &$query, DataQuery &$dataQuery)
    {
        // Convert reading mode to dataquery params and assign
        $args = ReadingMode::toDataQueryParams(Versioned::get_reading_mode());
        if ($args) {
            foreach ($args as $key => $value) {
                $dataQuery->setQueryParam($key, $value);
            }
        }
    }

    /**
     * Updates query parameters of relations attached to versioned dataobjects
     *
     * @param array $params
     */
    protected function updateInheritableQueryParams(&$params)
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
                $params['Versioned.stage'] = Versioned::DRAFT;
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
                    $params['Versioned.stage'] = Versioned::DRAFT;
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
     * @param DataQuery|null $dataQuery
     * @throws InvalidArgumentException
     */
    protected function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        if (!$dataQuery) {
            return;
        }

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
            case 'latest_version_single':
                $this->augmentSQLVersionedLatestSingle($query, $dataQuery);
                break;
            case 'latest_versions':
                $this->augmentSQLVersionedLatest($query, $dataQuery);
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
            $result['WasPublished'] ? Versioned::LIVE : Versioned::DRAFT,
        ];
        $this->versionModifiedCache[$key] = $list;
        return $list;
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
        ReadingMode::validateStage($stage);
        if ($stage === Versioned::DRAFT) {
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
        $excludingStage = $stage === Versioned::DRAFT ? Versioned::LIVE : Versioned::DRAFT;

        // Note that we double rename to avoid the regular stage rename
        // renaming all subquery references to be Versioned.stage
        $tempName = 'ExclusionarySource_' . $excludingStage;
        $excludingTable = $this->baseTable($excludingStage);
        $baseTable = $this->baseTable($stage);
        $query->addWhere("\"{$baseTable}\".\"ID\" NOT IN (SELECT \"ID\" FROM \"{$tempName}\")");
        $query->renameTable($tempName, $excludingTable);
    }

    /**
     * Augment SQL to select from `_Versions` table instead.
     *
     * @param SQLSelect $query
     * @param bool $filterDeleted Whether to exclude deleted entries or not
     */
    protected function augmentSQLVersioned(SQLSelect $query, bool $filterDeleted = true)
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
            // See ApplyVersionFilters for example which joins _Versions back onto draft table.
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
        if ($filterDeleted) {
            $query->addWhere(["\"{$baseTable}_Versions\".\"WasDeleted\"" => 0]);
        }
    }

    /**
     * Prepare a sub-select for determining latest versions of records on the base table. This is used as either an
     * inner join or sub-select on the base query
     *
     * @param SQLSelect $baseQuery
     * @param DataQuery $dataQuery
     * @return SQLSelect
     */
    protected function prepareMaxVersionSubSelect(SQLSelect $baseQuery, DataQuery $dataQuery)
    {
        $baseTable = $this->baseTable();

        // Create a sub-select that we determine latest versions
        $subSelect = SQLSelect::create(
            ['LatestVersion' => "MAX(\"{$baseTable}_Versions_Latest\".\"Version\")"],
            [$baseTable . '_Versions_Latest' => "\"{$baseTable}_Versions\""]
        );

        $subSelect->renameTable($baseTable, "{$baseTable}_Versions");

        // Determine the base table of the existing query
        $baseFrom = $baseQuery->getFrom();
        $baseTable = trim(reset($baseFrom) ?? '', '"');

        // And then the name of the base table in the new query
        $newFrom = $subSelect->getFrom();
        $newTable = trim(key($newFrom ?? []) ?? '', '"');

        // Parse "where" conditions to find those appropriate to be "promoted" into an inner join
        // We can ONLY promote a filter on the primary key of the base table. Any other conditions will make the
        // version returned incorrect, as we are filtering out version that may be the latest (and correct) version
        foreach ($baseQuery->getWhere() as $condition) {
            if (is_object($condition)) {
                continue;
            }
            $conditionClause = key($condition ?? []);
            // Pull out the table and field for this condition. We'll skip anything we can't parse
            if (preg_match('/^"([^"]+)"\."([^"]+)"/', $conditionClause ?? '', $matches) !== 1) {
                continue;
            }

            $table = $matches[1];
            $field = $matches[2];

            if ($table !== $baseTable || $field !== 'RecordID') {
                continue;
            }

            // Rename conditions on the base table to the new alias
            $conditionClause = preg_replace(
                '/^"([^"]+)"\./',
                "\"{$newTable}\".",
                $conditionClause ?? ''
            );

            $subSelect->addWhere([$conditionClause => reset($condition)]);
        }

        $shouldApplySubSelectAsCondition = $this->shouldApplySubSelectAsCondition($baseQuery);

        $this->owner->extend(
            'augmentMaxVersionSubSelect',
            $subSelect,
            $dataQuery,
            $shouldApplySubSelectAsCondition
        );

        return $subSelect;
    }

    /**
     * Indicates if a subquery filtering versioned records should apply as a condition instead of an inner join
     *
     * @param SQLSelect $baseQuery
     */
    protected function shouldApplySubSelectAsCondition(SQLSelect $baseQuery)
    {
        $baseTable = $this->baseTable();

        $shouldApply =
            $baseQuery->getLimit() === 1 || Config::inst()->get(static::class, 'use_conditions_over_inner_joins');

        $this->owner->extend('updateApplyVersionedFiltersAsConditions', $shouldApply, $baseQuery, $baseTable);

        return $shouldApply;
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

        // Query against _Versions table first
        $this->augmentSQLVersioned($query);

        // Validate stage
        $stage = $dataQuery->getQueryParam('Versioned.stage');
        ReadingMode::validateStage($stage);

        $subSelect = $this->prepareMaxVersionSubSelect($query, $dataQuery);

        $subSelect->addWhere(["\"{$baseTable}_Versions_Latest\".\"LastEdited\" <= ?" => $date]);

        // Filter on appropriate stage column in addition to date
        if ($this->hasStages()) {
            $stageColumn = $stage === Versioned::LIVE
                ? 'WasPublished'
                : 'WasDraft';
            $subSelect->addWhere("\"{$baseTable}_Versions_Latest\".\"{$stageColumn}\" = 1");
        }

        if ($this->shouldApplySubSelectAsCondition($query)) {
            $subSelect->addWhere(
                "\"{$baseTable}_Versions_Latest\".\"RecordID\" = \"{$baseTable}_Versions\".\"RecordID\""
            );

            $query->addWhere([
                "\"{$baseTable}_Versions\".\"Version\" = ({$subSelect->sql($params)})" => $params,
            ]);

            return;
        }

        $subSelect->addSelect("\"{$baseTable}_Versions_Latest\".\"RecordID\"");
        $subSelect->addGroupBy("\"{$baseTable}_Versions_Latest\".\"RecordID\"");

        // Join on latest version filtered by date
        $query->addInnerJoin(
            '(' . $subSelect->sql($params) . ')',
            <<<SQL
            "{$baseTable}_Versions_Latest"."RecordID" = "{$baseTable}_Versions"."RecordID"
            AND "{$baseTable}_Versions_Latest"."LatestVersion" = "{$baseTable}_Versions"."Version"
SQL
            ,
            "{$baseTable}_Versions_Latest",
            20,
            $params
        );
    }

    /**
     * Return latest version instance, regardless of whether it is on a particular stage.
     * This is similar to augmentSQLVersionedLatest() below, except it only returns a single value
     * selected by Versioned.id
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    protected function augmentSQLVersionedLatestSingle(SQLSelect $query, DataQuery $dataQuery)
    {
        $id = $dataQuery->getQueryParam('Versioned.id');
        if (!$id) {
            throw new InvalidArgumentException("Invalid id");
        }

        // Query against _Versions table first
        $this->augmentSQLVersioned($query);

        $baseTable = $this->baseTable();

        $query->addWhere(["\"$baseTable\".\"RecordID\"" => $id]);
        $query->setOrderBy("Version DESC");
        $query->setLimit(1);
    }

    /**
     * Return latest version instances, regardless of whether they are on a particular stage.
     * This provides "show all, including deleted" functionality.
     *
     * Note: latest_version ignores deleted versions, and will select the latest non-deleted
     * version.
     *
     * @param SQLSelect $query
     * @param DataQuery $dataQuery
     */
    protected function augmentSQLVersionedLatest(SQLSelect $query, DataQuery $dataQuery)
    {
        // Query against _Versions table first
        $this->augmentSQLVersioned($query);

        // Join and select only latest version
        $baseTable = $this->baseTable();
        $subSelect = $this->prepareMaxVersionSubSelect($query, $dataQuery);

        $subSelect->addWhere("\"{$baseTable}_Versions_Latest\".\"WasDeleted\" = 0");

        if ($this->shouldApplySubSelectAsCondition($query)) {
            $subSelect->addWhere(
                "\"{$baseTable}_Versions_Latest\".\"RecordID\" = \"{$baseTable}_Versions\".\"RecordID\""
            );

            $query->addWhere([
                "\"{$baseTable}_Versions\".\"Version\" = ({$subSelect->sql($params)})" => $params,
            ]);

            return;
        }

        $subSelect->addSelect("\"{$baseTable}_Versions_Latest\".\"RecordID\"");
        $subSelect->addGroupBy("\"{$baseTable}_Versions_Latest\".\"RecordID\"");

        // Join on latest version filtered by date
        $query->addInnerJoin(
            '(' . $subSelect->sql($params) . ')',
            <<<SQL
            "{$baseTable}_Versions_Latest"."RecordID" = "{$baseTable}_Versions"."RecordID"
            AND "{$baseTable}_Versions_Latest"."LatestVersion" = "{$baseTable}_Versions"."Version"
SQL
            ,
            "{$baseTable}_Versions_Latest",
            20,
            $params
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

        // Query against _Versions table first
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
        // Query against _Versions table first
        $this->augmentSQLVersioned($query, false);

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
        if (!is_a($tableClass, $baseClass ?? '', true)) {
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
}
