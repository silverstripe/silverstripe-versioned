<?php

namespace SilverStripe\Versioned;

use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\UnexpectedDataException;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use BadMethodCallException;
use Exception;
use LogicException;

/**
 * The ChangeSet model tracks several VersionedAndStaged objects for later publication as a single
 * atomic action
 *
 * @method HasManyList Changes()
 * @method Member Owner()
 * @property string $Name
 * @property string $State
 * @property bool $IsInferred
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
        'Name'  => 'Varchar',
        'State' => "Enum('open,published,reverted','open')",
        'IsInferred' => 'Boolean(0)', // True if created automatically
        'Description' => 'Text',
    ];

    private static $has_many = [
        'Changes' => ChangeSetItem::class,
    ];

    private static $defaults = [
        'State' => 'open'
    ];

    private static $has_one = [
        'Owner' => Member::class,
    ];

    private static $casting = [
        'Details' => 'Text',
    ];

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
        'ChangesCount' => 'Changes',
        'Details' => 'Details',
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
     * @throws Exception
     * @return bool True if successful
     */
    public function publish()
    {
        // Logical checks prior to publish
        if ($this->State !== static::STATE_OPEN) {
            throw new BadMethodCallException(
                "ChangeSet can't be published if it has been already published or reverted."
            );
        }
        if (!$this->isSynced()) {
            throw new ValidationException(
                "ChangeSet does not include all necessary changes and cannot be published."
            );
        }
        if (!$this->canPublish()) {
            throw new LogicException("The current member does not have permission to publish this ChangeSet.");
        }

        DB::get_conn()->withTransaction(function () {
            foreach ($this->Changes() as $change) {
                /** @var ChangeSetItem $change */
                $change->publish();
            }

            // Once this changeset is published, unlink any objects linking to
            // records in this changeset as unlinked (set RelationID to 0).
            // This is done as a safer alternative to deleting records on live that
            // are deleted on stage.
            foreach ($this->Changes() as $change) {
                /** @var ChangeSetItem $change */
                $change->unlinkDisownedObjects();
            }

            $this->State = static::STATE_PUBLISHED;
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
            'ObjectID'    => $object->ID,
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
            // TODO: Handle case of implicit added item being removed.

            $item->delete();
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
            return $item->ObjectClass.'.'.$item->ObjectID;
        }
        return $item->baseClass().'.'.$item->ID;
    }

    protected function calculateImplicit()
    {
        /** @var string[][] $explicit List of all items that have been explicitly added to this ChangeSet */
        $explicit = [];

        /** @var string[][] $referenced List of all items that are "referenced" by items in $explicit */
        $referenced = [];

        /** @var string[][] $references List of which explicit items reference each thing in referenced */
        $references = [];

        /** @var ChangeSetItem $item */
        foreach ($this->Changes()->filter(['Added' => ChangeSetItem::EXPLICITLY]) as $item) {
            $explicitKey = $this->implicitKey($item);
            $explicit[$explicitKey] = true;

            foreach ($item->findReferenced() as $referee) {
                try {
                    /** @var DataObject $referee */
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
        $implicit = array_diff_key($all, $explicit);

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
        // Start a transaction (if we can)
        DB::get_conn()->withTransaction(function () {

            // Get the implicitly included items for this ChangeSet
            $implicit = $this->calculateImplicit();

            // Adjust the existing implicit ChangeSetItems for this ChangeSet
            /** @var ChangeSetItem $item */
            foreach ($this->Changes()->filter(['Added' => ChangeSetItem::IMPLICITLY]) as $item) {
                $objectKey = $this->implicitKey($item);

                // If a ChangeSetItem exists, but isn't in $implicit, it's no longer required, so delete it
                if (!array_key_exists($objectKey, $implicit)) {
                    $item->delete();
                } // Otherwise it is required, so update ReferencedBy and remove from $implicit
                else {
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
            if (!array_key_exists($objectKey, $implicit)) {
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
        foreach ($this->Changes() as $change) {
            /** @var ChangeSetItem $change */
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
        /** @var ChangeSetItem $change */
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
            /** @var ChangeSetItem $change */
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
            $member = Member::currentUser();
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
            $fields->addFieldToTab('Root.Main', ReadonlyField::create('State', $this->fieldLabel('State')), 'Description');
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
        // Initialise list of items to count
        $counted = [];
        $countedOther = 0;
        foreach ($this->config()->important_classes as $type) {
            if (class_exists($type)) {
                $counted[$type] = 0;
            }
        }

        // Check each change item
        /** @var ChangeSetItem $change */
        foreach ($this->Changes() as $change) {
            $found = false;
            foreach ($counted as $class => $num) {
                if (is_a($change->ObjectClass, $class, true)) {
                    $counted[$class]++;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $countedOther++;
            }
        }

        // Describe set based on this output
        $counted = array_filter($counted);

        // Empty state
        if (empty($counted) && empty($countedOther)) {
            return '';
        }

        // Put all parts together
        $parts = [];
        foreach ($counted as $class => $count) {
            $parts[] = DataObject::singleton($class)->i18n_pluralise($count);
        }

        // Describe non-important items
        if ($countedOther) {
            if ($counted) {
                $parts[] = i18n::_t(
                    'SilverStripe\\Versioned\\ChangeSet.DESCRIPTION_OTHER_ITEM_PLURALS',
                    'one other item|{count} other items',
                    [ 'count' => $countedOther ]
                );
            } else {
                $parts[] = i18n::_t(
                    'SilverStripe\\Versioned\\ChangeSet.DESCRIPTION_ITEM_PLURALS',
                    'one item|{count} items',
                    [ 'count' => $countedOther ]
                );
            }
        }

        // Figure out how to join everything together
        if (empty($parts)) {
            return '';
        }
        if (count($parts) === 1) {
            return $parts[0];
        }

        // Non-comma list
        if (count($parts) === 2) {
            return _t(
                'SilverStripe\\Versioned\\ChangeSet.DESCRIPTION_AND',
                '{first} and {second}',
                [
                    'first' => $parts[0],
                    'second' => $parts[1],
                ]
            );
        }

        // First item
        $string = _t(
            'SilverStripe\\Versioned\\ChangeSet.DESCRIPTION_LIST_FIRST',
            '{item}',
            ['item' => $parts[0]]
        );

        // Middle items
        for ($i = 1; $i < count($parts) - 1; $i++) {
            $string = _t(
                'SilverStripe\\Versioned\\ChangeSet.DESCRIPTION_LIST_MID',
                '{list}, {item}',
                [
                    'list' => $string,
                    'item' => $parts[$i]
                ]
            );
        }

        // Oxford comma
        $string = _t(
            'SilverStripe\\Versioned\\ChangeSet.DESCRIPTION_LIST_LAST',
            '{list}, and {item}',
            [
                'list' => $string,
                'item' => end($parts)
            ]
        );
        return $string;
    }

    /**
     * Required to support count display in react gridfield column
     *
     * @return int
     */
    public function getChangesCount()
    {
        return $this->Changes()->count();
    }

    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Name'] = _t('SilverStripe\\Versioned\\ChangeSet.NAME', 'Name');
        $labels['State'] = _t('SilverStripe\\Versioned\\ChangeSet.STATE', 'State');

        return $labels;
    }
}
