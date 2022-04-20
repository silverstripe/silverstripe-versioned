<?php

namespace SilverStripe\Versioned;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;

/**
 * This class is a {@link GridField} component that replaces the delete action
 * and adds an archive action for objects.
 */

class GridFieldArchiveAction implements GridField_ColumnProvider, GridField_ActionProvider, GridField_ActionMenuItem
{
    /**
     * @inheritdoc
     */
    public function getTitle($gridField, $record, $columnName)
    {
        $field = $this->getArchiveAction($gridField, $record);

        if ($field) {
            return $field->getAttribute('title');
        }

        return _t(__CLASS__ . '.Delete', "Delete");
    }

    /**
     * @inheritdoc
     */
    public function getGroup($gridField, $record, $columnName)
    {
        $field = $this->getArchiveAction($gridField, $record);

        return $field ? GridField_ActionMenuItem::DEFAULT_GROUP: null;
    }

    /**
     *
     * @param GridField $gridField
     * @param DataObject $record
     * @param string $columnName
     * @return array|null the attributes for the action
     */
    public function getExtraData($gridField, $record, $columnName)
    {

        $field = $this->getArchiveAction($gridField, $record);

        if ($field) {
            return $field->getAttributes();
        }

        return null;
    }

    /**
     * Add a column 'Actions'
     *
     * @param GridField $gridField
     * @param array $columns
     */
    public function augmentColumns($gridField, &$columns)
    {
        $model = $gridField->getModelClass();
        $isModelVersioned = $model::has_extension(Versioned::class);
        if ($isModelVersioned) {
            $config = $gridField->getConfig();
            $deleteComponents = $config->getComponentsByType(GridFieldDeleteAction::class);
            foreach ($deleteComponents as $deleteComponent) {
                if ($deleteComponent->getRemoveRelation()) {
                    // The 'delete' button will "unlink" the relationship, NOT delete the item.
                    continue;
                }
                // Deleting an item that is published would leave no way to unpublish it.
                $config->removeComponent($deleteComponent);
            }
        }
        if (!in_array('Actions', $columns ?? [])) {
            $columns[] = 'Actions';
        }
    }

    /**
     * Return any special attributes that will be used for FormField::create_tag()
     *
     * @param GridField $gridField
     * @param DataObject $record
     * @param string $columnName
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return ['class' => 'grid-field__col-compact'];
    }

    /**
     * Add the title
     *
     * @param GridField $gridField
     * @param string $columnName
     * @return array
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        if ($columnName == 'Actions') {
            return ['title' => ''];
        }

        return [];
    }

    /**
     * Which columns are handled by this component
     *
     * @param GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField)
    {
        return ['Actions'];
    }

    /**
     * Which GridField actions are this component handling
     *
     * @param GridField $gridField
     * @return array
     */
    public function getActions($gridField)
    {
        return ['archiverecord'];
    }

    /**
     *
     * @param GridField $gridField
     * @param DataObject $record
     * @param string $columnName
     * @return string|null the HTML for the column
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        $field = $this->getArchiveAction($gridField, $record);

        if ($field) {
            return $field->Field();
        }

        return null;
    }

    /**
     * Handle the actions and apply any changes to the GridField
     *
     * @param GridField $gridField
     * @param string $actionName
     * @param array $arguments
     * @param array $data Form data
     * @throws ValidationException
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName === 'archiverecord') {
            /** @var DataObject|Versioned $item */
            $item = $gridField->getList()->byID($arguments['RecordID']);
            if (!$item) {
                return;
            }

            if (!$item->canArchive()) {
                throw new ValidationException(
                    _t(__CLASS__ . '.ArchivePermissionsFailure', "No archive permissions")
                );
            }

            $item->doArchive();
        }
    }

    /**
     * Returns the GridField_FormAction if archive can be performed
     *
     * @param GridField $gridField
     * @param DataObject $record
     * @return GridField_FormAction|null
     */
    public function getArchiveAction($gridField, $record)
    {
        /* @var DataObject|Versioned $record */
        if (!$record->hasMethod('canArchive') || !$record->canArchive()) {
            return null;
        }

        $title = _t(__CLASS__ . '.Archive', "Archive");

        $field = GridField_FormAction::create(
            $gridField,
            'ArchiveRecord' . $record->ID,
            false,
            "archiverecord",
            ['RecordID' => $record->ID]
        )
            ->addExtraClass('action--archive btn--icon-md font-icon-box btn--no-text grid-field__icon-action action-menu--handled')
            ->setAttribute('classNames', 'action--archive font-icon-box')
            ->setDescription($title)
            ->setAttribute('aria-label', $title);

        return $field;
    }
}
