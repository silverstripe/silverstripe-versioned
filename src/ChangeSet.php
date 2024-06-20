<?php

namespace SilverStripe\Versioned;

use BadMethodCallException;
use Exception;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\UnexpectedDataException;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * The ChangeSet model tracks several VersionedAndStaged objects for later publication as a single
 * atomic action
 *
 * @property string $Name
 * @property string $State one of 'open', 'published', or 'reverted'
 * @property bool $IsInferred
 * @property string $LastSynced Last synced date
 * @method HasManyList<ChangeSetItem> Changes()
 * @method Member Owner()
 * @method Member Publisher()
 */
class ChangeSet extends DataObject
{
    private static $singular_name = 'Campaign';

    private static $plural_name = 'Campaigns';

    /** An active changeset */
    const STATE_OPEN = 'open';

    /** A changeset which is reverted and closed */
    const STATE_REVERTED = 'reverted';

    /** A changeset which is published and closed */
    const STATE_PUBLISHED = 'published';

    private static $table_name = 'ChangeSet';

    private static $db = [
        'Name' => 'Varchar',
        'State' => "Enum('open,published,reverted','open')",
        'IsInferred' => 'Boolean(0)', // True if created automatically
        'Description' => 'Text',
        'PublishDate' => 'Datetime',
        'LastSynced' => 'Datetime',
    ];

    private static $has_many = [
        'Changes' => ChangeSetItem::class,
    ];

    private static $defaults = [
        'State' => 'open'
    ];

    private static $has_one = [
        'Owner' => Member::class,
        'Publisher' => Member::class,
    ];

    private static $casting = [
        'Details' => 'Text',
    ];

    private static $default_sort = '"ChangeSet"."State" ASC, "ChangeSet"."ID" ASC';

    /**
     * List of classes to set apart in description
     *
     * @config
     * @var array
     */
    private static $important_classes = [
        SiteTree::class,
        File::class,
    ];

    private static $summary_fields = [
        'Name' => 'Title',
        'Details' => 'Items',
        'StateLabel' => 'Status',
        'PublishedLabel' => 'Published',
    ];

    /**
     * Default permission to require for publishers.
     * Publishers must either be able to use the campaign admin, or have all admin access.
     *
     * Also used as default permission for ChangeSetItem default permission.
     *
     * @config
     * @var array
     */
    private static $required_permission = [
        'CMS_ACCESS_CampaignAdmin',
        'CMS_ACCESS_LeftAndMain'
    ];

    /**
     * Publish this changeset, then closes it.
     *
     * User code should call {@see canPublish()} prior to invoking this method.
     *
     * @throws BadMethodCallException ChangeSet has already been published or reverted.
     * @throws ValidationException ChangeSet is not synced an can not be published.
     * @param boolean $isSynced Whatever to assume the ChangeSet is synced. Only set this to true if the ChangetSet
     * just got built. Defaults to false.
     * @return bool True if successful
     */
    public function publish($isSynced = false)
    {
        // Logical checks prior to publish
        if ($this->State !== static::STATE_OPEN) {
            throw new BadMethodCallException(
                "ChangeSet can't be published if it has been already published or reverted."
            );
        }
        if (!$isSynced && !$this->isSynced()) {
            throw new ValidationException(
                "ChangeSet does not include all necessary changes and cannot be published."
            );
        }

        DB::get_conn()->withTransaction(function () {
            foreach ($this->Changes() as $change) {
                $change->publish();
            }

            // Once this changeset is published, unlink any objects linking to
            // records in this changeset as unlinked (set RelationID to 0).
            // This is done as a safer alternative to deleting records on live that
            // are deleted on stage.
            foreach ($this->Changes() as $change) {
                $change->unlinkDisownedObjects();
            }

            $this->State = static::STATE_PUBLISHED;
            $this->PublisherID = Security::getCurrentUser()
                ? Security::getCurrentUser()->ID
                : 0;
            $this->PublishDate = DBDatetime::now()->Rfc2822();

            $this->write();
        });
        return true;
    }

    /**
     * Add a new change to this changeset. Will automatically include all owned
     * changes as those are dependencies of this item.
     *
     * @param DataObject $object
     */
    public function addObject(DataObject $object)
    {
        if (!$this->isInDB()) {
            throw new BadMethodCallException("ChangeSet must be saved before adding items");
        }

        if (!$object->isInDB()) {
            throw new BadMethodCallException("Items must be saved before adding to a changeset");
        }

        $references = [
            'ObjectID' => $object->ID,
            'ObjectClass' => $object->baseClass(),
        ];

        // Get existing item in case already added
        $item = $this->Changes()->filter($references)->first();

        if (!$item) {
            $item = new ChangeSetItem($references);
            $this->Changes()->add($item);
        }

        $item->ReferencedBy()->removeAll();

        $item->Added = ChangeSetItem::EXPLICITLY;
        $item->write();

        $this->sync();
    }

    /**
     * Remove an item from this changeset. Will automatically remove all changes
     * which own (and thus depend on) the removed item.
     *
     * @param DataObject $object
     */
    public function removeObject(DataObject $object)
    {
        $item = ChangeSetItem::get()->filter([
            'ObjectID' => $object->ID,
            'ObjectClass' => $object->baseClass(),
            'ChangeSetID' => $this->ID
        ])->first();

        if ($item) {
            $item->delete();

            // Get the implicitly included items for this ChangeSet
            $implicit = $this->calculateImplicit();

            foreach ($this->Changes()->filter(['Added' => ChangeSetItem::IMPLICITLY]) as $changeSetItem) {
                $objectKey = $this->implicitKey($changeSetItem);

                // If a ChangeSetItem exists, but isn't in $implicit, it's no longer required, so delete it
                if (!array_key_exists($objectKey, $implicit)) {
                    $changeSetItem->delete();
                } else {
                    // Otherwise it is required, so update ReferencedBy and remove from $implicit
                    $changeSetItem->ReferencedBy()->setByIDList($implicit[$objectKey]['ReferencedBy']);
                    unset($implicit[$objectKey]);
                }
            }
        }

        $this->sync();
    }

    /**
     * Build identifying string key for this object
     *
     * @param DataObject $item
     * @return string
     */
    protected function implicitKey(DataObject $item)
    {
        if ($item instanceof ChangeSetItem) {
            return $item->ObjectClass . '.' . $item->ObjectID;
        }
        return $item->baseClass() . '.' . $item->ID;
    }

    /**
     * List of all implicit items inferred from all currently assigned explicit changes
     *
     * @return array
     */
    protected function calculateImplicit()
    {
        /** @var string[][] $explicit List of all items that have been explicitly added to this ChangeSet */
        $explicit = [];

        /** @var string[][] $referenced List of all items that are "referenced" by items in $explicit */
        $referenced = [];

        /** @var string[][] $references List of which explicit items reference each thing in referenced */
        $references = [];

        foreach ($this->Changes()->filter(['Added' => ChangeSetItem::EXPLICITLY]) as $item) {
            $explicitKey = $this->implicitKey($item);
            $explicit[$explicitKey] = true;

            foreach ($item->findReferenced() as $referee) {
                try {
                    $key = $this->implicitKey($referee);

                    $referenced[$key] = [
                        'ObjectID' => $referee->ID,
                        'ObjectClass' => $referee->baseClass(),
                    ];

                    $references[$key][] = $item->ID;

                    // Skip any bad records
                } catch (UnexpectedDataException $e) {
                }
            }
        }

        /** @var string[][] $explicit List of all items that are either in $explicit, $referenced or both */
        $all = array_merge($referenced, $explicit);

        /** @var string[][] $implicit Anything that is in $all, but not in $explicit, is an implicit inclusion */
        $implicit = array_diff_key($all ?? [], $explicit);

        foreach ($implicit as $key => $object) {
            $implicit[$key]['ReferencedBy'] = $references[$key];
        }

        return $implicit;
    }

    /**
     * Add implicit changes that should be included in this changeset
     *
     * When an item is created or changed, all it's owned items which have
     * changes are implicitly added
     *
     * When an item is deleted, it's owner (even if that owner does not have changes)
     * is implicitly added
     */
    public function sync()
    {
        // Only sync open changesets
        if ($this->State !== static::STATE_OPEN) {
            return;
        }

        // Start a transaction (if we can)
        DB::get_conn()->withTransaction(function () {

            // Get the implicitly included items for this ChangeSet
            $implicit = $this->calculateImplicit();

            // Adjust the existing implicit ChangeSetItems for this ChangeSet
            foreach ($this->Changes()->filter(['Added' => ChangeSetItem::IMPLICITLY]) as $item) {
                $objectKey = $this->implicitKey($item);

                // If a ChangeSetItem exists, but isn't in $implicit, it's no longer required, so delete it
                if (!array_key_exists($objectKey, $implicit ?? [])) {
                    $item->delete();
                } else {
                    // Otherwise it is required, so update ReferencedBy and remove from $implicit
                    $item->ReferencedBy()->setByIDList($implicit[$objectKey]['ReferencedBy']);
                    unset($implicit[$objectKey]);
                }
            }

            // Now $implicit is all those items that are implicitly included, but don't currently have a ChangeSetItem.
            // So create new ChangeSetItems to match

            foreach ($implicit as $key => $props) {
                $item = new ChangeSetItem($props);
                $item->Added = ChangeSetItem::IMPLICITLY;
                $item->ChangeSetID = $this->ID;
                $item->ReferencedBy()->setByIDList($props['ReferencedBy']);
                $item->write();
            }

            // Mark last synced
            $this->LastSynced = DBDatetime::now()->getValue();
            $this->write();
        });
    }

    /** Verify that any objects in this changeset include all owned changes */
    public function isSynced()
    {
        $implicit = $this->calculateImplicit();

        // Check the existing implicit ChangeSetItems for this ChangeSet

        foreach ($this->Changes()->filter(['Added' => ChangeSetItem::IMPLICITLY]) as $item) {
            $objectKey = $this->implicitKey($item);

            // If a ChangeSetItem exists, but isn't in $implicit -> validation failure
            if (!array_key_exists($objectKey, $implicit ?? [])) {
                return false;
            }
            // Exists, remove from $implicit
            unset($implicit[$objectKey]);
        }

        // If there's anything left in $implicit -> validation failure
        return empty($implicit);
    }

    public function canView($member = null)
    {
        return $this->can(__FUNCTION__, $member);
    }

    public function canEdit($member = null)
    {
        return $this->can(__FUNCTION__, $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return $this->can(__FUNCTION__, $member, $context);
    }

    public function canDelete($member = null)
    {
        return $this->can(__FUNCTION__, $member);
    }

    /**
     * Check if this item is allowed to be published
     *
     * @param Member $member
     * @return bool
     */
    public function canPublish($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        // Check all explicitly added items
        foreach ($this->Changes()->filter(['Added' => ChangeSetItem::EXPLICITLY]) as $change) {
            if (!$change->canPublish($member)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if there are changes to publish
     *
     * @return bool
     */
    public function hasChanges()
    {
        // All changes must be publishable
        foreach ($this->Changes() as $change) {
            if ($change->hasChange()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if this changeset (if published) can be reverted
     *
     * @param Member $member
     * @return bool
     */
    public function canRevert($member = null)
    {
        // All changes must be publishable
        foreach ($this->Changes() as $change) {
            if (!$change->canRevert($member)) {
                return false;
            }
        }

        // Default permission
        return $this->can(__FUNCTION__, $member);
    }

    /**
     * Default permissions for this changeset
     *
     * @param string $perm
     * @param Member $member
     * @param array $context
     * @return bool
     */
    public function can($perm, $member = null, $context = [])
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        // Allow extensions to bypass default permissions, but only if
        // each change can be individually published.
        $extended = $this->extendedCan($perm, $member, $context);
        if ($extended !== null) {
            return $extended;
        }

        // Default permissions
        return (bool)Permission::checkMember($member, $this->config()->required_permission);
    }

    public function getCMSFields()
    {
        $fields = new FieldList(new TabSet('Root'));
        if ($this->IsInferred) {
            $fields->addFieldToTab('Root.Main', ReadonlyField::create('Name', $this->fieldLabel('Name')));
            $fields->addFieldToTab('Root.Main', ReadonlyField::create('Description', $this->fieldLabel('Description')));
        } else {
            $fields->addFieldToTab('Root.Main', TextField::create('Name', $this->fieldLabel('Name')));
            $fields->addFieldToTab('Root.Main', TextareaField::create('Description', $this->fieldLabel('Description')));
        }
        if ($this->isInDB()) {
            $fields->addFieldToTab(
                'Root.Main',
                ReadonlyField::create('State', $this->fieldLabel('State')),
                'Description'
            );
        }
        if ($this->Publisher()->exists()) {
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    ReadonlyField::create(
                        'PublishDate',
                        $this->fieldLabel('PublishDate')
                    ),
                    ReadonlyField::create(
                        'PublisherName',
                        $this->fieldLabel('PublisherName'),
                        $this->getPublisherName()
                    )
                ],
                'Description'
            );
        }

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     * Gets summary of items in changeset
     *
     * @return string
     */
    public function getDetails()
    {
        // Check each change item
        $total = 0;
        $withChanges = 0;
        foreach ($this->Changes() as $change) {
            $total++;
            if ($change->hasChange()) {
                $withChanges++;
            }
        }

        // Empty state
        if (empty($total)) {
            return _t(__CLASS__ . '.EMPTY', 'Empty');
        }

        // Count all items
        $totalText = _t(
            __CLASS__ . '.ITEMS_TOTAL',
            '1 total|{count} total',
            ['count' => $total]
        );
        if (empty($withChanges)) {
            return $totalText;
        }

        // Interpolate with changes text
        return _t(
            __CLASS__ . '.ITEMS_CHANGES',
            '{total} (1 change)|{total} ({count} changes)',
            [
                'total' => $totalText,
                'count' => $withChanges,
            ]
        );
    }

    /**
     * Required to support the "changes" count display in react gridfield column
     *
     * @return int
     */
    public function getChangesCount()
    {
        return $this->Changes()->count();
    }

    /**
     * Gets the label for the "last published" date. Special case for "today"
     *
     * @return string
     */
    public function getPublishedLabel()
    {
        $dateObj = $this->obj('PublishDate');
        if (!$dateObj) {
            return null;
        }

        $publisher = $this->getPublisherName();

        // Use "today"
        if ($dateObj->IsToday()) {
            if ($publisher) {
                return _t(
                    __CLASS__ . '.PUBLISHED_TODAY_BY',
                    'Today {time} by {name}',
                    [
                        'time' => $dateObj->Time12(),
                        'name' => $publisher,
                    ]
                );
            }
            // Today, no publisher
            return _t(
                __CLASS__ . '.PUBLISHED_TODAY',
                'Today {time}',
                ['time' => $dateObj->Time12()]
            );
        }

        // Use date
        if ($publisher) {
            return _t(
                __CLASS__ . '.PUBLISHED_DATE_BY',
                '{date} by {name}',
                [
                    'date' => $dateObj->FormatFromSettings(),
                    'name' => $publisher,
                ]
            );
        }

        // Date, no publisher
        return $dateObj->FormatFromSettings();
    }

    /**
     * Description for state
     *
     * @return string
     */
    public function getStateLabel()
    {
        switch ($this->State) {
            case ChangeSet::STATE_OPEN:
                return _t(__CLASS__.'.STATE_OPEN', 'Active');
            case ChangeSet::STATE_PUBLISHED:
                return _t(__CLASS__.'.STATE_PUBLISHED', 'Published');
            case ChangeSet::STATE_REVERTED:
                return _t(__CLASS__.'.STATE_REVERTED', 'Reverted');
            default:
                return null;
        }
    }

    /**
     * Gets the full name of the user who last published this campaign
     *
     * @return string
     */
    public function getPublisherName()
    {
        if ($this->Publisher()->exists()) {
            return $this->Publisher()->getName();
        }

        return null;
    }

    /**
     * @param bool $includerelations
     * @return array
     */
    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Name'] = _t(__CLASS__ . '.NAME', 'Name');
        $labels['State'] = _t(__CLASS__ . '.STATE', 'State');
        $labels['PublishDate'] = _t(__CLASS__.'.PUBLISH_DATE', 'Publish date');
        $labels['PublisherName'] = _t(__CLASS__.'.PUBLISHER_NAME', 'Published by');
        return $labels;
    }

    public function provideI18nEntities()
    {
        return array_merge(
            parent::provideI18nEntities(),
            [
                __CLASS__ . '.ITEMS_TOTAL' => [
                    'one' => '1 total',
                    'other' => '{count} total',
                ],
                __CLASS__ . '.ITEMS_CHANGES' => [
                    'one' => '{total} (1 change)',
                    'other' => '{total} ({count} changes)',
                ],
            ]
        );
    }
}
