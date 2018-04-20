<?php

namespace SilverStripe\Versioned;

use BadMethodCallException;
use InvalidArgumentException;
use LogicException;
use SilverStripe\Assets\Thumbnail;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\CMSPreviewable;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\UnexpectedDataException;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * A single line in a changeset
 *
 * @property string $Added
 * @property string $ObjectClass The _base_ data class for the referenced DataObject
 * @property int $ObjectID The numeric ID for the referenced object
 * @property int $ChangeSetID ID of parent ChangeSet object
 * @property int $VersionBefore
 * @property int $VersionAfter
 * @method ManyManyList ReferencedBy() List of explicit items that require this change
 * @method ManyManyList References() List of implicit items required by this change
 * @method ChangeSet ChangeSet() Parent changeset
 * @method DataObject Object() The object attached to this item
 */
class ChangeSetItem extends DataObject implements Thumbnail
{

    const EXPLICITLY = 'explicitly';

    const IMPLICITLY = 'implicitly';

    /** Represents an object deleted */
    const CHANGE_DELETED = 'deleted';

    /** Represents an object which was modified */
    const CHANGE_MODIFIED = 'modified';

    /** Represents an object added */
    const CHANGE_CREATED = 'created';

    private static $table_name = 'ChangeSetItem';

    /**
     * Represents that an object has not yet been changed, but
     * should be included in this changeset as soon as any changes exist.
     * Also used for unversioned objects that have no non-recursive publish.
     */
    const CHANGE_NONE = 'none';

    private static $db = [
        'VersionBefore' => 'Int',
        'VersionAfter' => 'Int',
        'Added' => "Enum('explicitly, implicitly', 'implicitly')"
    ];

    private static $has_one = [
        'ChangeSet' => ChangeSet::class,
        'Object' => DataObject::class,
    ];

    private static $many_many = [
        'ReferencedBy' => ChangeSetItem::class,
    ];

    private static $belongs_many_many = [
        'References' => ChangeSetItem::class . '.ReferencedBy',
    ];

    private static $indexes = [
        'ObjectUniquePerChangeSet' => [
            'type' => 'unique',
            'columns' => ['ObjectID', 'ObjectClass', 'ChangeSetID'],
        ]
    ];

    public function onBeforeWrite()
    {
        // Make sure ObjectClass refers to the base data class in the case of old or wrong code
        $this->ObjectClass = $this->getSchema()->baseDataClass($this->ObjectClass);
        parent::onBeforeWrite();
    }

    public function getTitle()
    {
        // Get title of modified object
        $object = $this->getObjectLatestVersion();
        if ($object) {
            return $object->getTitle();
        }
        return $this->i18n_singular_name() . ' #' . $this->ID;
    }

    /**
     * Get a thumbnail for this object
     *
     * @param int $width Preferred width of the thumbnail
     * @param int $height Preferred height of the thumbnail
     * @return string URL to the thumbnail, if available
     */
    public function ThumbnailURL($width, $height)
    {
        $object = $this->getObjectLatestVersion();
        if ($object instanceof Thumbnail) {
            return $object->ThumbnailURL($width, $height);
        }
        return null;
    }

    /**
     * Get the type of change: none, created, deleted, modified, manymany
     * @return string
     * @throws UnexpectedDataException
     */
    public function getChangeType()
    {
        if (!class_exists($this->ObjectClass)) {
            throw new UnexpectedDataException("Invalid Class '{$this->ObjectClass}' in ChangeSetItem #{$this->ID}");
        }

        // Unversioned classes have no change type
        if (!$this->isVersioned()) {
            return self::CHANGE_NONE;
        }

        // Get change versions
        if ($this->VersionBefore || $this->VersionAfter) {
            $draftVersion = $this->VersionAfter; // After publishing draft was written to stage
            $liveVersion = $this->VersionBefore; // The live version before the publish
        } else {
            $draftVersion = Versioned::get_versionnumber_by_stage(
                $this->ObjectClass,
                Versioned::DRAFT,
                $this->ObjectID,
                false
            );
            $liveVersion = Versioned::get_versionnumber_by_stage(
                $this->ObjectClass,
                Versioned::LIVE,
                $this->ObjectID,
                false
            );
        }

        // Version comparisons
        if ($draftVersion == $liveVersion) {
            $type = self::CHANGE_NONE;
        } elseif (!$liveVersion) {
            $type = self::CHANGE_CREATED;
        } elseif (!$draftVersion) {
            $type = self::CHANGE_DELETED;
        } else {
            $type = self::CHANGE_MODIFIED;
        }
        $this->extend('updateChangeType', $type, $draftVersion, $liveVersion);
        return $type;
    }

    /**
     * Find version of this object in the given stage.
     * If the object isn't versioned it will return the normal record.
     *
     * @param string $stage
     * @return DataObject|Versioned|RecursivePublishable Object in this stage (may not be Versioned)
     * @throws UnexpectedDataException
     */
    protected function getObjectInStage($stage)
    {
        if (!class_exists($this->ObjectClass)) {
            throw new UnexpectedDataException("Invalid Class '{$this->ObjectClass}' in ChangeSetItem #{$this->ID}");
        }

        // Ignore stage for unversioned objects
        if (!$this->isVersioned()) {
            return DataObject::get_by_id($this->ObjectClass, $this->ObjectID);
        }

        // Get versioned object
        return Versioned::get_by_stage($this->ObjectClass, $stage)->byID($this->ObjectID);
    }

    /**
     * Find latest version of this object
     * @return DataObject|Versioned
     * @throws UnexpectedDataException
     */
    protected function getObjectLatestVersion()
    {
        if (!class_exists($this->ObjectClass)) {
            throw new UnexpectedDataException("Invalid Class '{$this->ObjectClass}' in ChangeSetItem #{$this->ID}");
        }

        // Ignore version for unversioned objects
        if (!$this->isVersioned()) {
            return DataObject::get_by_id($this->ObjectClass, $this->ObjectID);
        }

        // Get versioned object
        return Versioned::get_latest_version($this->ObjectClass, $this->ObjectID);
    }

    /**
     * Get all implicit objects for this change
     *
     * @return SS_List
     */
    public function findReferenced()
    {
        $liveRecord = $this->getObjectInStage(Versioned::LIVE);

        // For unversioned objects, simply return all owned objects
        if (!$this->isVersioned()) {
            return $liveRecord->findOwned();
        }

        // If we have deleted this record, recursively delete live objects on publish
        if ($this->getChangeType() === ChangeSetItem::CHANGE_DELETED) {
            if (!$liveRecord) {
                return ArrayList::create();
            }
            return $liveRecord->findCascadeDeletes(true);
        }

        // If changed on stage, include all owned objects for publish
        /** @var DataObject|RecursivePublishable $draftRecord */
        $draftRecord = $this->getObjectInStage(Versioned::DRAFT);
        if (!$draftRecord) {
            return ArrayList::create();
        }
        $references = $draftRecord->findOwned();

        // When publishing, use cascade_deletes to partially unpublished sets
        if ($liveRecord) {
            foreach ($liveRecord->findCascadeDeletes(true) as $next) {
                /** @var Versioned|DataObject $next */
                if ($next->hasExtension(Versioned::class) && $next->hasStages() && $next->isOnLiveOnly()) {
                    $this->mergeRelatedObject($references, ArrayList::create(), $next);
                }
            }
        }
        return $references;
    }

    /**
     * Publish this item, then close it.
     *
     * Note: Unlike Versioned::doPublish() and Versioned::doUnpublish, this action is not recursive.
     */
    public function publish()
    {
        if (!class_exists($this->ObjectClass)) {
            throw new UnexpectedDataException("Invalid Class '{$this->ObjectClass}' in ChangeSetItem #{$this->ID}");
        }

        // Logical checks prior to publish
        if ($this->VersionBefore || $this->VersionAfter) {
            throw new BadMethodCallException("This ChangeSetItem has already been published");
        }

        // Skip unversioned records
        if (!$this->isVersioned()) {
            $this->VersionBefore = 0;
            $this->VersionAfter = 0;
            $this->write();
            return;
        }

        // Record state changed
        $this->VersionAfter = Versioned::get_versionnumber_by_stage(
            $this->ObjectClass,
            Versioned::DRAFT,
            $this->ObjectID,
            false
        );
        $this->VersionBefore = Versioned::get_versionnumber_by_stage(
            $this->ObjectClass,
            Versioned::LIVE,
            $this->ObjectID,
            false
        );

        // Enact change
        $changeType = $this->getChangeType();
        switch ($changeType) {
            case static::CHANGE_NONE: {
                break;
            }
            case static::CHANGE_DELETED: {
                // Non-recursive delete
                $object = $this->getObjectInStage(Versioned::LIVE);
                $object->deleteFromStage(Versioned::LIVE);
                break;
            }
            case static::CHANGE_MODIFIED:
            case static::CHANGE_CREATED: {
                // Non-recursive publish
                $object = $this->getObjectInStage(Versioned::DRAFT);
                $object->publishSingle();

                // Point after version to the published version actually created, not the
                // version copied from draft.
                $this->VersionAfter = Versioned::get_versionnumber_by_stage(
                    $this->ObjectClass,
                    Versioned::LIVE,
                    $this->ObjectID,
                    false
                );
                break;
            }
            default:
                throw new LogicException("Invalid change type: {$changeType}");
        }

        $this->write();
    }

    /**
     * Once this item (and all owned objects) are published, unlink
     * all disowned objects
     */
    public function unlinkDisownedObjects()
    {
        $object = $this->getObjectInStage(Versioned::DRAFT);
        if ($object) {
            $object->unlinkDisownedObjects($object, Versioned::LIVE);
        }
    }

    /** Reverts this item, then close it. **/
    public function revert()
    {
        user_error('Not implemented', E_USER_ERROR);
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
     * Check if the BeforeVersion of this changeset can be restored to draft
     *
     * @param Member $member
     * @return bool
     */
    public function canRevert($member)
    {
        // No action for unversiond objects so no action to deny
        if (!$this->isVersioned()) {
            return true;
        }

        // Just get the best version as this object may not even exist on either stage anymore.
        /** @var Versioned|DataObject $object */
        $object = $this->getObjectLatestVersion();
        if (!$object) {
            return false;
        }

        // Check change type
        switch ($this->getChangeType()) {
            case static::CHANGE_CREATED: {
                // Revert creation by deleting from stage
                return $object->canDelete($member);
            }
            default: {
                // All other actions are typically editing draft stage
                return $object->canEdit($member);
            }
        }
    }

    /**
     * Check if this ChangeSetItem can be published
     *
     * @param Member $member
     * @return bool
     */
    public function canPublish($member = null)
    {
        // No action for unversiond objects so no action to deny
        // Implicitly added items allow publish
        if (!$this->isVersioned() || $this->Added === self::IMPLICITLY) {
            return true;
        }

        // Check canMethod to invoke on object
        switch ($this->getChangeType()) {
            case static::CHANGE_DELETED: {
                /** @var Versioned|DataObject $object */
                $object = Versioned::get_by_stage($this->ObjectClass, Versioned::LIVE)->byID($this->ObjectID);
                if ($object) {
                    return $object->canUnpublish($member);
                }
                break;
            }
            default: {
                /** @var Versioned|DataObject $object */
                $object = Versioned::get_by_stage($this->ObjectClass, Versioned::DRAFT)->byID($this->ObjectID);
                if ($object) {
                    return $object->canPublish($member);
                }
                break;
            }
        }

        return true;
    }

    /**
     * Determine if this item has changes
     *
     * @return bool
     */
    public function hasChange()
    {
        return $this->getChangeType() !== ChangeSetItem::CHANGE_NONE;
    }

    /**
     * Default permissions for this ChangeSetItem
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
        return (bool)Permission::checkMember($member, ChangeSet::config()->get('required_permission'));
    }

    /**
     * Get the ChangeSetItems that reference a passed DataObject
     *
     * @param DataObject $object
     * @return DataList
     */
    public static function get_for_object($object)
    {
        // Capture changesetitem for both changed and deleted objects
        $id = $object->isInDB()
            ? $object->ID
            : $object->OldID;
        return static::get_for_object_by_id($id, $object->baseClass());
    }

    /**
     * Get the ChangeSetItems that reference a passed DataObject
     *
     * @param int $objectID The ID of the object
     * @param string $objectClass The class of the object (or any parent class)
     * @return DataList
     */
    public static function get_for_object_by_id($objectID, $objectClass)
    {
        if (!$objectID) {
            throw new InvalidArgumentException("Cannot get ChangesetItem for object which was never saved");
        }
        return ChangeSetItem::get()->filter([
            'ObjectID' => $objectID,
            'ObjectClass' => static::getSchema()->baseDataClass($objectClass)
        ]);
    }

    /**
     * Gets the list of modes this record can be previewed in.
     *
     * {@link https://tools.ietf.org/html/draft-kelly-json-hal-07#section-5}
     *
     * @return array Map of links in acceptable HAL format
     */
    public function getPreviewLinks()
    {
        $links = [];

        // Preview draft
        $stage = $this->getObjectInStage(Versioned::DRAFT);
        if ($stage instanceof CMSPreviewable && $stage->canView() && ($link = $stage->PreviewLink())) {
            $links[Versioned::DRAFT] = [
                'href' => Controller::join_links($link, '?stage=' . Versioned::DRAFT),
                'type' => $stage->getMimeType(),
            ];
        }

        // Preview live if versioned
        if ($this->isVersioned()) {
            $live = $this->getObjectInStage(Versioned::LIVE);
            if ($live instanceof CMSPreviewable && $live->canView() && ($link = $live->PreviewLink())) {
                $links[Versioned::LIVE] = [
                'href' => Controller::join_links($link, '?stage=' . Versioned::LIVE),
                'type' => $live->getMimeType(),
                ];
            }
        }

        return $links;
    }

    /**
     * Get edit link for this item
     *
     * @return string
     */
    public function CMSEditLink()
    {
        $link = $this->getObjectInStage(Versioned::DRAFT);
        if ($link instanceof CMSPreviewable) {
            return $link->CMSEditLink();
        }
        return null;
    }

    /**
     * Check if the object attached to this changesetitem is versionable
     *
     * @return bool
     */
    public function isVersioned()
    {
        if (!$this->ObjectClass || !class_exists($this->ObjectClass)) {
            return false;
        }
        /** @var Versioned|DataObject $singleton */
        $singleton = DataObject::singleton($this->ObjectClass);
        return $singleton->hasExtension(Versioned::class) && $singleton->hasStages();
    }
}
