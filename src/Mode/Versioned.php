<?php

namespace SilverStripe\Versioned\Mode;

use InvalidArgumentException;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Resettable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\View\TemplateGlobalProvider;
use SilverStripe\Versioned\Mode\Traits\VersionedAugmentSomethingTrait;
use SilverStripe\Versioned\Mode\Traits\VersionedAugmentSqlTrait;
use SilverStripe\Versioned\Mode\Traits\VersionedCanChecksTrait;
use SilverStripe\Versioned\Mode\Traits\VersionedHookImplementationsTrait;
use SilverStripe\Versioned\Mode\Traits\VersionedIsMethodsTrait;
use SilverStripe\Versioned\Mode\Traits\VersionedPublicMethodsTrait;
use SilverStripe\Versioned\Mode\Traits\VersionedStaticMethodsTrait;

/**
 * The Versioned extension allows your DataObjects to have several versions,
 * allowing you to rollback changes and view history. An example of this is
 * the pages used in the CMS.
 *
 * Note: This extension relies on the object also having the {@see Ownership} extension applied.
 *
 * @property int $Version
 * @mixin RecursivePublishable
 *
 * @extends Extension<DataObject&RecursivePublishable&static>
 */
class Versioned extends Extension implements TemplateGlobalProvider, Resettable
{
    use VersionedAugmentSomethingTrait;
    use VersionedAugmentSqlTrait;
    use VersionedPublicMethodsTrait;
    use VersionedStaticMethodsTrait;
    use VersionedCanChecksTrait;
    use VersionedIsMethodsTrait;
    use VersionedHookImplementationsTrait;

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
     * Cache of version to modified dates for this object
     *
     * @var array
     */
    protected $versionModifiedCache = [];

    /**
     * A cache used by get_versionnumber_by_stage().
     * Clear through {@link flushCache()}.
     * version (int)0 means not on this stage.
     *
     * @var array
     */
    protected static $cache_versionnumber;

    /**
     * Field used to hold the migrating version
     */
    const MIGRATING_VERSION = 'MigratingVersion';

    /**
     * Field used to hold flag indicating the next write should be without a new version
     */
    const NEXT_WRITE_WITHOUT_VERSIONED = 'NextWriteWithoutVersioned';

    /**
     * Prevents delete() from creating a _Versions record (in case this must be deferred)
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
        Versioned::MIGRATING_VERSION,
        Versioned::NEXT_WRITE_WITHOUT_VERSIONED,
        Versioned::DELETE_WRITES_VERSION_DISABLED,
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
        "WasDraft" => "Boolean(1)",
        "AuthorID" => "Int",
        "PublisherID" => "Int"
    ];

    /**
     * Ensure versioned records cast extra fields properly
     *
     * @config
     * @var array
     */
    private static $casting = [
        "RecordID" => "Int",
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
     * Indicates whether augmentSQL operations should add subselects as WHERE conditions instead of INNER JOIN
     * intersections. Performance of the INNER JOIN scales on the size of _Versions tables where as the condition scales
     * on the number of records being returned from the base query.
     *
     * @config
     * @var bool
     */
    private static $use_conditions_over_inner_joins = false;

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
    private static $non_live_permissions = [
        'CMS_ACCESS_LeftAndMain',
        'CMS_ACCESS_CMSMain',
        'VIEW_DRAFT_CONTENT',
        'CAN_DEV_BUILD'
    ];

    /**
     * Use PHP's session storage for the "reading mode" and "unsecuredDraftSite",
     * instead of explicitly relying on the "stage" query parameter.
     * This is considered bad practice, since it can cause draft content
     * to leak under live URLs to unauthorised users, depending on HTTP cache settings.
     *
     * @config
     * @var bool
     */
    private static $use_session = false;

    /**
     * Construct a new Versioned object.
     *
     * @var string $mode One of "StagedVersioned" or "Versioned".
     */
    public function __construct($mode = Versioned::STAGEDVERSIONED)
    {
        if (!in_array($mode, [Versioned::STAGEDVERSIONED, Versioned::VERSIONED])) {
            throw new InvalidArgumentException("Invalid mode: {$mode}");
        }

        $this->mode = $mode;
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
        if ($this->hasStages() && $stage === Versioned::LIVE) {
            return "{$table}_{$stage}";
        }
        return $table;
    }

    /**
     * Returns an array of possible stages.
     *
     * @return array
     */
    public function getVersionedStages()
    {
        if ($this->hasStages()) {
            return [Versioned::DRAFT, Versioned::LIVE];
        } else {
            return [Versioned::DRAFT];
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
        return $this->mode === Versioned::STAGEDVERSIONED;
    }

    /**
     * Get author of this record.
     * Note: Only works on records selected via Versions()
     *
     * @return Member|null
     */
    public function Author()
    {
        if (!$this->owner->AuthorID) {
            return null;
        }
        $member = DataObject::get_by_id(Member::class, $this->owner->AuthorID);
        return $member;
    }
    /**
     * Get publisher of this record.
     * Note: Only works on records selected via Versions()
     *
     * @return Member|null
     */
    public function Publisher()
    {
        if (!$this->owner->PublisherID) {
            return null;
        }
        $member = DataObject::get_by_id(Member::class, $this->owner->PublisherID);
        return $member;
    }
}
