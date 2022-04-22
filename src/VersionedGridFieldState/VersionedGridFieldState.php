<?php

namespace SilverStripe\Versioned\VersionedGridFieldState;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ColumnProvider;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Core\Convert;
use SilverStripe\View\HTML;

class VersionedGridFieldState implements GridField_ColumnProvider
{
    /**
     * @var string
     */
    protected $column = null;

    /**
     * Fields/columns to display version states. We can specifies more than one
     * field but states only show in the first column found.
     *
     * @array
     */
    protected $versionedLabelFields = [];

    public function __construct($versionedLabelFields = ['Name', 'Title'])
    {
        $this->setVersionedLabelFields($versionedLabelFields);
    }

    /**
     * Column to decorate with version state
     *
     * @return string
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @param string $column
     * @return VersionedGridFieldState
     */
    public function setColumn($column)
    {
        $this->column = $column;
        return $this;
    }

    /**
     * Search list for default column
     *
     * @return array
     */
    public function getVersionedLabelFields()
    {
        return $this->versionedLabelFields;
    }

    /**
     * @param array $versionedLabelFields
     * @return VersionedGridFieldState
     */
    public function setVersionedLabelFields($versionedLabelFields)
    {
        $this->versionedLabelFields = $versionedLabelFields;
        return $this;
    }

    /**
     * Modify the list of columns displayed in the table.
     *
     * @see {@link GridFieldDataColumns->getDisplayFields()}
     * @see {@link GridFieldDataColumns}.
     *
     * @param GridField $gridField
     * @param array $columns List reference of all column names.
     */
    public function augmentColumns($gridField, &$columns)
    {
        $model = $gridField->getModelClass();
        $isModelVersioned = $model::has_extension(Versioned::class);

        // Skip if not versioned, or column already set
        if (!$isModelVersioned || $this->getColumn()) {
            return;
        }

        $matchedVersionedFields = array_intersect(
            $columns ?? [],
            $this->versionedLabelFields
        );

        if (count($matchedVersionedFields ?? []) > 0) {
            // Get first matched column
            $this->setColumn(reset($matchedVersionedFields));
        } elseif ($columns) {
            // Use first column if none of preferred matches
            $this->setColumn(reset($columns));
        }
    }

    /**
     * Names of all columns which are affected by this component.
     *
     * @param GridField $gridField
     * @return array
     */
    public function getColumnsHandled($gridField)
    {
        return $this->getColumn() ? [$this->getColumn()] : [];
    }

    /**
     * HTML for the column, content of the <td> element.
     *
     * @param GridField $gridField
     * @param DataObject $record Record displayed in this row
     * @param string $columnName
     * @return string HTML for the column. Return NULL to skip.
     */
    public function getColumnContent($gridField, $record, $columnName)
    {
        $flagContent = '';
        $flags = $this->getStatusFlags($record);
        foreach ($flags as $class => $data) {
            $flagAttributes = [
                'class' => "ss-gridfield-badge badge status-{$class}",
            ];
            if (isset($data['title'])) {
                $flagAttributes['title'] = $data['title'];
            }
            $flagContent .= ' ' . HTML::createTag('span', $flagAttributes, Convert::raw2xml($data['text']));
        }
        return $flagContent;
    }

    /**
     * Attributes for the element containing the content returned by {@link getColumnContent()}.
     *
     * @param  GridField $gridField
     * @param  DataObject $record displayed in this row
     * @param  string $columnName
     * @return array
     */
    public function getColumnAttributes($gridField, $record, $columnName)
    {
        return [];
    }

    /**
     * Additional metadata about the column which can be used by other components,
     * e.g. to set a title for a search column header.
     *
     * @param GridField $gridField
     * @param string $columnName
     * @return array - Map of arbitrary metadata identifiers to their values.
     */
    public function getColumnMetadata($gridField, $columnName)
    {
        return [];
    }


    /**
     * A flag provides the user with additional data about the current item
     * status, for example a "removed from draft" status. Each item can have
     * more than one status flag. Returns a map of a unique key to a
     * (localized) title for the flag. The unique key can be reused as a CSS
     * class.
     *
     * Example (simple):
     *
     * ```php
     *   "deletedonlive" => "Deleted"
     * ```
     *
     * Example (with optional title attribute):
     *
     * ```php
     *   "deletedonlive" => array(
     *      'text' => "Deleted",
     *      'title' => 'This page has been deleted'
     *   )
     * ```
     *
     * @param Versioned|DataObject $record - the record to check status for
     * @return array
     */
    protected function getStatusFlags($record)
    {
        if (!$record->hasExtension(Versioned::class)) {
            return [];
        }

        if ($record->isOnLiveOnly()) {
            return [
                'removedfromdraft' => [
                    'text' => _t(__CLASS__ . '.ONLIVEONLYSHORT', 'On live only'),
                    'title' => _t(
                        __CLASS__ . '.ONLIVEONLYSHORTHELP',
                        'Item is published, but has been deleted from draft'
                    ),
                ]
            ];
        }

        if ($record->isArchived()) {
            return [
                'archived' => [
                    'text' => _t(__CLASS__ . '.ARCHIVEDPAGESHORT', 'Archived'),
                    'title' => _t(__CLASS__ . '.ARCHIVEDPAGEHELP', 'Item is removed from draft and live'),
                ]
            ];
        }

        if ($record->isOnDraftOnly()) {
            return [
                'addedtodraft' => [
                    'text' => _t(__CLASS__ . '.ADDEDTODRAFTSHORT', 'Draft'),
                    'title' => _t(__CLASS__ . '.ADDEDTODRAFTHELP', "Item has not been published yet")
                ]
            ];
        }

        if ($record->isModifiedOnDraft()) {
            return [
                'modified' => [
                    'text' => _t(__CLASS__ . '.MODIFIEDONDRAFTSHORT', 'Modified'),
                    'title' => _t(__CLASS__ . '.MODIFIEDONDRAFTHELP', 'Item has unpublished changes'),
                ]
            ];
        }

        return [];
    }
}
