<?php

namespace SilverStripe\Versioned;

use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;

/**
 * Generic restore action to restore an archived item to draft
 */
class RestoreAction
{
    /**
     * Returns a message which notifies the user of a successful restoration
     * and if anything has changed
     *
     * @param Object $item
     * @return array $message
     * @throws ValidationException if the record cannot be restored
     */
    public static function restore($item)
    {
        $isArchived = $item->isArchived();
        $canRestoreToDraft = $item->canRestoreToDraft();

        if (!$canRestoreToDraft) {
            throw new ValidationException(
                _t(
                    __CLASS__ . '.RESTORE_FALIURE_PERMISSION',
                    'Insufficient permission to restore item'
                ),
                403
            );
        }

        if (!$isArchived) {
            throw new ValidationException(
                _t(
                    __CLASS__ . '.RESTORE_FALIURE_STATE',
                    'This item already exists and cannot be restored'
                ),
                409
            );
        }

        $classname = $item->classname;
        $id = $item->ID;

        if (!$classname || !$id) {
            return new ValidationException(
                _t(
                    __CLASS__ . '.RESTORE_FALIURE_RECORD',
                    'Unable to restore item'
                ),
                400
            );
        }

        $changedLocation = RestoreAction::shouldRestoreToRoot($item);

        $archivedItem = Versioned::get_latest_version($classname, $id);

        if (method_exists($archivedItem, 'doRestoreToStage')) {
            $restoredItem = $archivedItem->doRestoreToStage();
        } else {
            $archivedItem->writeToStage(Versioned::DRAFT);
            $restoredItem = Versioned::get_by_stage($archivedItem->classname, Versioned::DRAFT)
                ->byID($archivedItem->ID);
        }

        $message = RestoreAction::getRestoreMessage($item, $restoredItem, $changedLocation);

        return $message;
    }

    /**
     * Returns a message which notifies the user of a successful restoration
     * and if anything has changed
     *
     * @param $originalItem
     * @param $restoredItem,
     * @param bool $changedLocation
     * @return array $message
     */
    public static function getRestoreMessage($originalItem, $restoredItem, $changedLocation = false)
    {
        $restoredID = $restoredItem->Title ?: $restoredItem->ID;
        $restoredType = strtolower($restoredItem->i18n_singular_name() ?? '');

        if (method_exists($restoredItem, 'CMSEditLink') &&
        $restoredItem->CMSEditLink()) {
            $restoredID = sprintf('<a href="%s">%s</a>', $restoredItem->CMSEditLink(), $restoredID);
        }

        if ($originalItem->URLSegment !== $restoredItem->URLSegment) {
            $changedProperty = [
                'property' => 'URL',
                'value' => '../' . $restoredItem->URLSegment
            ];
        } elseif ($originalItem->Title !== $restoredItem->Title) {
            $changedProperty = [
                'property' => 'Name',
                'value' => $restoredItem->Title
            ];
        }

        if ($changedLocation) {
            $message = [
                'text' => _t(
                    __CLASS__ . '.RESTORE_CHANGEDLOCATION',
                    'Restored the {model} "{id}" to the top level as original location cannot be found.',
                    [
                        'model' => $restoredType,
                        'id' => $restoredID
                    ]
                ),
                'type' => 'notice',
            ];
        } elseif (isset($changedProperty)) {
            $message = [
                'text' => _t(
                    __CLASS__ . '.RESTORE_CHANGEDPROPERTY',
                    'A {model} already exists with the same {property}. "{id}" has been restored with a new {property} ({value}).',
                    [
                        'model' => $restoredType,
                        'property' => $changedProperty['property'],
                        'id' => $restoredID,
                        'value' => $changedProperty['value']
                    ]
                ),
                'type' => 'warning',
            ];
        } else {
            $message = [
                'text' => _t(
                    __CLASS__ . '.RESTORE_SUCCESS',
                    'Successfully restored the {model} "{id}"',
                    [
                        'model' => $restoredType,
                        'id' => $restoredID
                    ]
                ),
                'type' => 'good',
            ];
        }

        return $message;
    }

    /**
     * Determines whether this record can be restored to it's original location
     *
     * @param $record
     * @return bool
     */
    public static function shouldRestoreToRoot($record)
    {
        // If the record had a parent and that no longer exists in draft then yes
        if ($record->hasExtension(Hierarchy::class) && $record->ParentID != false) {
            return $record->getParent() === null;
        }

        // Otherwise it should be restored normally
        return false;
    }
}
