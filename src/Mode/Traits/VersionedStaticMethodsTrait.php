<?php

namespace SilverStripe\Versioned\Mode\Traits;

use SilverStripe\Versioned\Mode\Versioned;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Mode\ReadingMode;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\Director;
use SilverStripe\ORM\DB;
use SilverStripe\Control\Controller;
use InvalidArgumentException;
use SilverStripe\ORM\DataList;

trait VersionedStaticMethodsTrait
{
    /**
     * Set if draft site is secured or not. Fails over to
     * $draft_site_secured if unset
     *
     * @var bool|null
     */
    protected static $is_draft_site_secured = null;

    /**
     * Default config for $is_draft_site_secured
     *
     * @config
     * @var bool
     */
    private static $draft_site_secured = true;

    /**
     * Current reading mode. Supports stage / archive modes.
     *
     * @var string
     */
    protected static $reading_mode = null;

    /**
     * Default reading mode, if none set.
     * Any modes which differ to this value should be assigned via querystring / session (if enabled)
     *
     * @var null
     */
    protected static $default_reading_mode = Versioned::DEFAULT_MODE;

    /**
     * Reset static configuration variables to their default values.
     */
    public static function reset()
    {
        Versioned::$reading_mode = '';
        Controller::curr()->getRequest()->getSession()->clear('readingMode');
    }

    /**
     * Determine if the current user is able to set the given site stage / archive
     *
     * @param HTTPRequest $request
     * @return bool
     */
    public static function can_choose_site_stage($request)
    {
        // Request is allowed if stage isn't being modified
        if ((!$request->getVar('stage') || $request->getVar('stage') === Versioned::LIVE)
            && !$request->getVar('archiveDate')
        ) {
            return true;
        }

        // Request is allowed if unsecuredDraftSite is enabled
        if (!Versioned::get_draft_site_secured()) {
            return true;
        }

        // Predict if choose_site_stage() will allow unsecured draft assignment by session
        if (Config::inst()->get(Versioned::class, 'use_session') && $request->getSession()->get('unsecuredDraftSite')) {
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
        $mode = Versioned::get_default_reading_mode();

        // Check any pre-existing session mode
        $useSession = Config::inst()->get(Versioned::class, 'use_session');
        $updateSession = false;
        if ($useSession) {
            // Boot reading mode from session
            $mode = $request->getSession()->get('readingMode') ?: $mode;

            // Set draft site security if disabled for this session
            if ($request->getSession()->get('unsecuredDraftSite')) {
                Versioned::set_draft_site_secured(false);
            }
        }

        // Verify if querystring contains valid reading mode
        $queryMode = ReadingMode::fromQueryString($request->getVars());
        if ($queryMode) {
            $mode = $queryMode;
            $updateSession = true;
        }

        // Save reading mode
        Versioned::set_reading_mode($mode);

        // Set mode if session enabled
        if ($useSession && $updateSession) {
            $request->getSession()->set('readingMode', $mode);
        }

        if (!headers_sent() && !Director::is_cli()) {
            if (Versioned::get_stage() === Versioned::LIVE) {
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
        Versioned::$reading_mode = $mode;
    }

    /**
     * Get the current reading mode.
     *
     * @return string
     */
    public static function get_reading_mode()
    {
        return Versioned::$reading_mode;
    }

    /**
     * Get the current reading stage.
     *
     * @return string
     */
    public static function get_stage()
    {
        $parts = explode('.', Versioned::get_reading_mode() ?? '');

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
        $parts = explode('.', Versioned::get_reading_mode() ?? '');
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
        $parts = explode('.', Versioned::get_reading_mode() ?? '');
        if (sizeof($parts ?? []) === 3 && $parts[0] == 'Archive') {
            return $parts[2];
        }
        return Versioned::DRAFT;
    }

    /**
     * Set the reading stage.
     *
     * @param string $stage New reading stage.
     * @throws InvalidArgumentException
     */
    public static function set_stage($stage)
    {
        ReadingMode::validateStage($stage);
        Versioned::set_reading_mode('Stage.' . $stage);
    }

    /**
     * Replace default mode.
     * An non-default mode should be specified via querystring arguments.
     *
     * @param string $mode
     */
    public static function set_default_reading_mode($mode)
    {
        Versioned::$default_reading_mode = $mode;
    }

    /**
     * Get default reading mode
     *
     * @return string
     */
    public static function get_default_reading_mode()
    {
        return Versioned::$default_reading_mode ?: Versioned::DEFAULT_MODE;
    }

    /**
     * Check if draft site should be secured.
     * Can be turned off if draft site unauthenticated
     *
     * @return bool
     */
    public static function get_draft_site_secured()
    {
        if (isset(Versioned::$is_draft_site_secured)) {
            return (bool)Versioned::$is_draft_site_secured;
        }
        // Config default
        return (bool)Config::inst()->get(Versioned::class, 'draft_site_secured');
    }

    /**
     * Set if the draft site should be secured or not
     *
     * @param bool $secured
     */
    public static function set_draft_site_secured($secured)
    {
        Versioned::$is_draft_site_secured = $secured;
    }

    /**
     * Set the reading archive date.
     *
     * @param string $date New reading archived date.
     * @param string $stage Set stage
     */
    public static function reading_archived_date($date, $stage = Versioned::DRAFT)
    {
        ReadingMode::validateStage($stage);
        Versioned::set_reading_mode('Archive.' . $date . '.' . $stage);
    }

    /**
     * Get a singleton instance of a class in the given stage.
     *
     * @template T of DataObject
     * @param class-string<T> $class The name of the class.
     * @param string $stage The name of the stage.
     * @param string $filter A filter to be inserted into the WHERE clause.
     * @param boolean $cache Use caching.
     * @param string $sort A sort expression to be inserted into the ORDER BY clause.
     * @return T&static
     */
    public static function get_one_by_stage($class, $stage, $filter = '', $cache = true, $sort = '')
    {
        return Versioned::withVersionedMode(function () use ($class, $stage, $filter, $cache, $sort) {
            Versioned::set_stage($stage);
            return DataObject::get_one($class, $filter, $cache, $sort);
        });
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
        $version = static::determineVersionNumberByStage($class, $stage, $id, $cache);
        $className = $class instanceof DataObject ? $class->ClassName : $class;
        $object = DataObject::singleton($className);
        $object->invokeWithExtensions('updateGetVersionNumberByStage', $version, $class, $stage, $id, $cache);

        return $version;
    }

    /**
     * @param DataObject|string $class
     * @param string $stage
     * @param int $id
     * @param bool $cache
     * @return int|null
     */
    private static function determineVersionNumberByStage($class, $stage, $id, $cache)
    {
        ReadingMode::validateStage($stage);
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        $stageTable = DataObject::getSchema()->tableName($baseClass);
        if ($stage === Versioned::LIVE) {
            $stageTable .= "_{$stage}";
        }

        // cached call
        if ($cache) {
            if (isset(Versioned::$cache_versionnumber[$baseClass][$stage][$id])) {
                return Versioned::$cache_versionnumber[$baseClass][$stage][$id] ?: null;
            } elseif (isset(Versioned::$cache_versionnumber[$baseClass][$stage]['_complete'])) {
                // if the cache was marked as "complete" then we know the record is missing, just return null
                // this is used for treeview optimisation to avoid unnecessary re-requests for draft pages
                return null;
            }
        }

        // get version as performance-optimized SQL query (gets called for each record in the sitetree)
        $version = DB::prepared_query(
            "SELECT \"Version\" FROM \"$stageTable\" WHERE \"ID\" = ?",
            [$id]
        )->value();

        // cache value (if required)
        if ($cache) {
            if (!isset(Versioned::$cache_versionnumber[$baseClass])) {
                Versioned::$cache_versionnumber[$baseClass] = [];
            }

            if (!isset(Versioned::$cache_versionnumber[$baseClass][$stage])) {
                Versioned::$cache_versionnumber[$baseClass][$stage] = [];
            }

            // Internally store nulls as 0
            Versioned::$cache_versionnumber[$baseClass][$stage][$id] = $version ?: 0;
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
        ReadingMode::validateStage($stage);
        if (!Config::inst()->get(Versioned::class, 'prepopulate_versionnumber_cache')) {
            return;
        }

        $singleton = DataObject::singleton($class);
        $baseClass = $singleton->baseClass();
        $baseTable = $singleton->baseTable();
        $stageTable = $singleton->stageTable($baseTable, $stage);

        $filter = "";
        $parameters = [];
        if ($idList) {
            // Validate the ID list
            foreach ($idList as $id) {
                if (!is_numeric($id)) {
                    throw new InvalidArgumentException(
                        "Bad ID passed to Versioned::prepopulate_versionnumber_cache() in \$idList: " . $id
                    );
                }
            }
            $filter = 'WHERE "ID" IN (' . DB::placeholders($idList) . ')';
            $parameters = $idList;

        // If we are caching IDs for _all_ records then we can mark this cache as "complete" and in the case of a cache-miss
        // no subsequent call is necessary
        } else {
            Versioned::$cache_versionnumber[$baseClass][$stage] = [ '_complete' => true ];
        }

        $versions = DB::prepared_query("SELECT \"ID\", \"Version\" FROM \"$stageTable\" $filter", $parameters)->map();

        foreach ($versions as $id => $version) {
            Versioned::$cache_versionnumber[$baseClass][$stage][$id] = $version;
        }

        $className = $class instanceof DataObject ? $class->ClassName : $class;
        $object = DataObject::singleton($className);
        $object->invokeWithExtensions('updatePrePopulateVersionNumberCache', $versions, $class, $stage, $idList);
    }

    /**
     * Get a set of class instances by the given stage.
     *
     * @template T of DataObject
     * @param class-string<T> $class The name of the class.
     * @param string $stage The name of the stage.
     * @param string $filter A filter to be inserted into the WHERE clause.
     * @param string $sort A sort expression to be inserted into the ORDER BY clause.
     * @param string $join Deprecated, use leftJoin($table, $joinClause) instead
     * @param int $limit A limit on the number of records returned from the database.
     * @param string $containerClass The container class for the result set (default is DataList)
     *
     * @return DataList<T> A modified DataList designated to the specified stage
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
        ReadingMode::validateStage($stage);
        $result = DataObject::get($class, $filter, $sort, $join, $limit, $containerClass);
        return $result->setDataQueryParam([
            'Versioned.mode' => 'stage',
            'Versioned.stage' => $stage
        ]);
    }

    /**
     * Return the latest version of the given record.
     *
     * @template T of DataObject
     * @param class-string<T> $class
     * @param int $id
     * @return T&static
     */
    public static function get_latest_version($class, $id)
    {
        $baseClass = DataObject::getSchema()->baseDataClass($class);
        $list = DataList::create($baseClass)
            ->setDataQueryParam([
                "Versioned.mode" => 'latest_version_single',
                "Versioned.id" => $id
            ]);
        return $list->first();
    }


    /**
     * Return the equivalent of a DataList::create() call, querying the latest
     * version of each record stored in the (class)_Versions tables.
     *
     * In particular, this will query deleted records as well as active ones.
     *
     * @template T of DataObject
     * @param class-string<T> $class
     * @param string $filter
     * @param string $sort
     * @return DataList<T>
     */
    public static function get_including_deleted($class, $filter = "", $sort = "")
    {
        $list = DataList::create($class);
        if (!empty($filter)) {
            $list = $list->where($filter);
        }
        if (!empty($sort)) {
            $list = $list->orderBy($sort);
        }
        $list = $list->setDataQueryParam("Versioned.mode", "latest_versions");
        return $list;
    }

    /**
     * Return the specific version of the given id.
     *
     * Caution: The record is retrieved as a DataObject, but saving back
     * modifications via write() will create a new version, rather than
     * modifying the existing one.
     *
     * @template T of DataObject
     * @param class-string<T> $class
     * @param int $id
     * @param int $version
     * @return T&static
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
     * @template T
     * @param class-string<T> $class
     * @param int $id
     *
     * @return DataList<T>
     */
    public static function get_all_versions($class, $id)
    {
        $list = DataList::create($class)
            ->filter('ID', $id)
            ->setDataQueryParam('Versioned.mode', 'all_versions');

        return $list;
    }

    /**
     * Invoke a callback which may modify reading mode, but ensures this mode is restored
     * after completion, without modifying global state.
     *
     * The desired reading mode should be set by the callback directly
     *
     * @param callable $callback
     * @return mixed Result of $callback
     */
    public static function withVersionedMode($callback)
    {
        $origReadingMode = Versioned::get_reading_mode();
        try {
            return $callback();
        } finally {
            Versioned::set_reading_mode($origReadingMode);
        }
    }
}
