<?php

namespace SilverStripe\Versioned\Traits;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Versioned\Versioned_Version;
use SilverStripe\ORM\DataList;

/**
 * Functionality to cache Versioned::Versions(), which is used for the <DataObject>_Versions tables
 * This trait exists to help encapsulate specific functionality for the Versioned class.
 * Its use with any other class is not supported.
 * @internal
 */
trait VersionsCacheTrait
{
    /**
     * In-memory cache of Versions i.e. the <DataObject>_Versions table
     * @internal
     */
    private static array $versions_cache = [];

    /**
     * Prepopulate the in-memory Versions cache, which are used for <DataObject>_Versions tables
     * The values will be retrieved using an efficient `WHERE "ID" IN (<ids>)` SQL query
     * Note method this should not to be confused with prepopulateVersionNumberCache()
     */
    public static function prepopulateVersionsCache(string $baseDataClass, array $ids): void
    {
        $cachedIDs = [];
        foreach ($ids as $id) {
            $key = self::class::generateVersionsCacheKey($id);
            if ($key && array_key_exists($key, self::class::$versions_cache)) {
                $cachedIDs[] = $id;
            }
        }
        // Only fetch uncached IDs
        $ids = array_diff($ids, $cachedIDs);
        if (empty($ids)) {
            return;
        }
        $versions = self::class::innerReadVersions($baseDataClass, $ids);
        foreach ($ids as $id) {
            $key = self::class::generateVersionsCacheKey($id);
            if ($key) {
                $vers = array_filter($versions, fn($ver) => (int) $ver->RecordID === $id);
                self::class::$versions_cache[$key] = ArrayList::create($vers);
            }
        }
    }

    /**
     * Get an ArrayList of Versions for an ID
     *
     * @return ArrayList<Versioned_Version>
     */
    private static function readVersions(
        DataObject $owner,
        mixed $filter,
        mixed $sort,
        mixed $limit,
        mixed $join,
    ): ArrayList {
        // When an object is not yet in the Database, we can't get its versions
        if (!$owner->isInDB()) {
            return ArrayList::create();
        }
        $id = $owner->ID;
        $key = self::class::generateVersionsCacheKey($id, $filter, $sort, $limit, $join);
        if ($key && array_key_exists($key, self::class::$versions_cache)) {
            return self::class::$versions_cache[$key];
        }
        $baseDataClass = DataObject::getSchema()->baseDataClass($owner);
        $versions = self::class::innerReadVersions($baseDataClass, [$id], $filter, $sort, $limit);
        $list = ArrayList::create($versions);
        return $list;
    }

    /**
     * Internal shared logic used by prepopulateVersionsCache() and readVersionsID()
     */
    private static function innerReadVersions(
        string $baseDataClass,
        array $ids,
        mixed $filter = '',
        mixed $sort = '',
        mixed $limit = '',
    ): array {
        $oldMode = Versioned::get_reading_mode();
        Versioned::set_stage(Versioned::DRAFT);
        $list = DataObject::get($baseDataClass, $filter, $sort, $limit);
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
        foreach (array_keys(Config::inst()->get(Versioned::class, 'db_for_versions_table')) as $name) {
            $query->selectField(sprintf('"%s_Versions"."%s"', $baseTable, $name), $name);
        }
        $usePlaceholders = DataList::config()->get('use_placeholders_for_integer_ids');
        // Check for non int IDs to protected against SQL injection
        $notInts = array_filter($ids, fn($id) => !ctype_digit((string) $id) || $id != (int) $id);
        if ($usePlaceholders || count($notInts)) {
            $in = DB::placeholders($ids);
            $query->addWhere(["\"{$baseTable}_Versions\".\"RecordID\" IN ($in)" => $ids]);
        } else {
            $in = implode(', ', $ids);
            $query->addWhere("\"{$baseTable}_Versions\".\"RecordID\" IN ($in)");
        }
        $query->setOrderBy(($sort)
            ? $sort
            : "\"{$baseTable}_Versions\".\"LastEdited\" DESC, \"{$baseTable}_Versions\".\"Version\" DESC");
        $records = $query->execute();
        $versions = [];
        foreach ($records as $record) {
            $versions[] = Versioned_Version::create($record);
        }
        Versioned::set_reading_mode($oldMode);
        return $versions;
    }

    /**
     * Generate a key for $versions_cache
     * For the mixed params in this method, ensure they are seralizable before passing them to this method
     * @return string|false The key as a string or false if a key was unable to be generated
     */
    private static function generateVersionsCacheKey(
        int $id,
        mixed $filter = '',
        mixed $sort = '',
        mixed $limit = '',
        mixed $join = '',
    ): string|false {
        $keyInputs = [$filter, $sort, $limit, $join];
        $isSerializable = fn(mixed $var) => @serialize($var) !== false;
        $allSerializable = count(array_filter($keyInputs, $isSerializable)) === count($keyInputs);
        if (!$allSerializable) {
            return false;
        }
        return $id . '_' . md5(implode('', array_map('serialize', $keyInputs)));
    }
}
