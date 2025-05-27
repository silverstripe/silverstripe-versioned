<?php

namespace SilverStripe\Versioned\Traits;

use InvalidArgumentException;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\ReadingMode;
use SilverStripe\Versioned\Versioned;

/**
 * This trait exists to help encapsulate specific functionality for the Versioned class.
 * Its use with any other class is not supported.
 * @internal
 */
trait VersionNumberCacheTrait
{
    /**
     * A cache used by get_versionnumber_by_stage().
     * Clear through {@link flushCache()}.
     * A value of 0 in [$baseClass][$stage][$id] means a record with that ID is not in that stage.
     *
     * @var array
     */
    protected static $cache_versionnumber;

    /**
     * Used to enable or disable the prepopulation of the version number cache.
     * Set this configuration on the extension, not on the class being extended. For example:
     * <code>
     * SilverStripe\Versioned\Versioned:
     *    prepopulate_versionnumber_cache: false
     * </code>
     * Defaults to true.
     *
     * @var boolean
     */
    private static $prepopulate_versionnumber_cache = true;

    /**
     * Shorthand method to call prepopulateVersionNumberCacheForStage() for all applicable stages
     * This caches the version numbers of a particular DataObject class
     * Not to be confused with prepopulateVersionsCache()
     */
    public static function prepopulateVersionNumberCache(string $dataClass, ?array $ids = null): void
    {
        $stages = [Versioned::DRAFT];
        if ($dataClass::has_extension(Versioned::class)) {
            $stages[] = Versioned::LIVE;
        }
        foreach ($stages as $stage) {
            self::class::prepopulateVersionNumberCacheForStage($dataClass, $stage, $ids);
        }
    }

    /**
     * Pre-populate the cache for Versioned::get_versionnumber_by_stage() for
     * a list of record IDs, for more efficient database querying.
     * If the $ids param is null, then every record will be cached.
     */
    public static function prepopulateVersionNumberCacheForStage(
        string $dataClass,
        string $stage,
        ?array $ids = null,
    ): void {
        $ids ??= [];
        ReadingMode::validateStage($stage);
        if (!Config::inst()->get(static::class, 'prepopulate_versionnumber_cache')) {
            return;
        }
        $singleton = DataObject::singleton($dataClass);
        $baseClass = $singleton->baseClass();
        $baseTable = $singleton->baseTable();
        $stageTable = $singleton->stageTable($baseTable, $stage);
        $usePlaceholders = DataList::config()->get('use_placeholders_for_integer_ids');
        $parameters = null;
        $filter = '';
        if (count($ids)) {
            // Check for non int IDs to protected against SQL injection
            $notInts = array_filter($ids, fn($id) => !ctype_digit((string) $id) || $id != (int) $id);
            if ($usePlaceholders || count($notInts)) {
                $filter = 'WHERE "ID" IN (' . DB::placeholders($ids) . ')';
                $parameters = $ids;
            } else {
                $filter = 'WHERE "ID" IN (' . implode(', ', $ids) . ')';
            }
        } else {
            // If we are caching IDs for _all_ records then we can mark this cache as "complete" and in the case of a cache-miss
            // no subsequent call is necessary
            self::class::$cache_versionnumber[$baseClass][$stage] = [ '_complete' => true ];
        }
        $sql = "SELECT \"ID\", \"Version\" FROM \"$stageTable\" $filter";
        if ($usePlaceholders) {
            $versions = DB::prepared_query($sql, $parameters)->map();
        } else {
            $versions = DB::query($sql)->map();
        }
        if ($ids) {
            foreach ($ids as $id) {
                $version = 0;
                foreach ($versions as $vid => $val) {
                    if ($id === $vid) {
                        $version = $val;
                        break;
                    }
                }
                self::class::$cache_versionnumber[$baseClass][$stage][$id] = $version;
            }
        } else {
            foreach ($versions as $id => $version) {
                self::class::$cache_versionnumber[$baseClass][$stage][$id] = $version;
            }
        }
        $object = DataObject::singleton($dataClass);
        $object->invokeWithExtensions('updatePrePopulateVersionNumberCache', $versions, $dataClass, $stage, $ids);
    }
}
