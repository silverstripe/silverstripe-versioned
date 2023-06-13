<?php

namespace SilverStripe\Versioned;

use SilverStripe\Assets\Image;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\Deprecation;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Parsers\Diff;
use SilverStripe\View\ViewableData;

/**
 * Utility class to render views of the differences between two data objects (or two versions of the
 * same data object).
 *
 * Construcing a diff object is done as follows:
 * <code>
 * $fromRecord = Versioned::get_version('SiteTree', $pageID, $fromVersion);
 * $toRecord = Versioned::get_version('SiteTree, $pageID, $toVersion);
 * $diff = DataDifferencer::create($fromRecord, $toRecord);
 * </code>
 *
 * And then it can be used in a number of ways.  You can use the ChangedFields() method in a template:
 * <pre>
 * <dl class="diff">
 * <% with Diff %>
 * <% loop ChangedFields %>
 *    <dt>$Title</dt>
 *    <dd>$Diff</dd>
 * <% end_loop %>
 * <% end_with %>
 * </dl>
 * </pre>
 *
 * Or you can get the diff'ed content as another DataObject, that you can insert into a form.
 * <code>
 * $form->loadDataFrom($diff->diffedData());
 * </code>
 *
 * If there are fields whose changes you aren't interested in, you can ignore them like so:
 * <code>
 * $diff->ignoreFields('AuthorID', 'Status');
 * </code>
 */
class DataDifferencer extends ViewableData
{
    protected $fromRecord;
    protected $toRecord;

    protected $ignoredFields = ["ID","Version","RecordID"];

    /**
     * Construct a DataDifferencer to show the changes between $fromRecord and $toRecord.
     * If $fromRecord is null, this will represent a "creation".
     *
     * @param DataObject $fromRecord
     * @param DataObject $toRecord
     */
    public function __construct(DataObject $fromRecord = null, DataObject $toRecord = null)
    {
        $this->fromRecord = $fromRecord;
        $this->toRecord = $toRecord;
        parent::__construct();
    }

    /**
     * Specify some fields to ignore changes from.  Repeated calls are cumulative.
     * @param array $ignoredFields An array of field names to ignore.  Alternatively, pass the field names as
     * separate args.
     * @return $this
     */
    public function ignoreFields($ignoredFields)
    {
        if (!is_array($ignoredFields)) {
            $ignoredFields = func_get_args();
        }
        $this->ignoredFields = array_merge($this->ignoredFields, $ignoredFields);

        return $this;
    }

    /**
     * Get a DataObject with altered values replaced with HTML diff strings, incorporating
     * <ins> and <del> tags.
     */
    public function diffedData()
    {
        if ($this->fromRecord) {
            $diffed = clone $this->fromRecord;
            $fields = array_keys($diffed->toMap() + $this->toRecord->toMap());
        } else {
            $diffed = clone $this->toRecord;
            $fields = array_keys($this->toRecord->toMap() ?? []);
        }

        $hasOnes = array_merge($this->fromRecord->hasOne(), $this->toRecord->hasOne());

        // Loop through properties
        foreach ($fields as $field) {
            if (in_array($field, $this->ignoredFields ?? [])) {
                continue;
            }
            if (in_array($field, array_keys($hasOnes ?? []))) {
                continue;
            }

            // Check if a field from-value is comparable
            $toField = $this->toRecord->obj($field);
            if (!($toField instanceof DBField)) {
                continue;
            }
            $toValue = $toField->forTemplate();

            // Show only to value
            if (!$this->fromRecord) {
                $diffed->setField($field, DBField::create_field('HTMLFragment', "<ins>{$toValue}</ins>"));
                continue;
            }

            // Check if a field to-value is comparable
            $fromField = $this->fromRecord->obj($field);
            if (!($fromField instanceof DBField)) {
                continue;
            }
            $fromValue = $fromField->forTemplate();

            // Show changes between the two, if any exist
            if ($fromValue != $toValue) {
                $diffValue = Deprecation::withNoReplacement(function () use ($fromValue, $toValue) {
                    return Diff::compareHTML($fromValue, $toValue);
                });
                $diffed->setField($field, DBField::create_field('HTMLFragment', $diffValue));
            }
        }

        // Loop through has_one
        foreach ($hasOnes as $relName => $relSpec) {
            if (in_array($relName, $this->ignoredFields ?? [])) {
                continue;
            }

            // Create the actual column name
            $relField = "{$relName}ID";
            // Using relation name instead of database column name, because of FileField etc.
            $setField = is_a($relSpec, Image::class, true) ? $relName : $relField;
            $toTitle = '';
            /** @var DataObject $relObjTo */
            $relObjTo = null;
            if ($this->toRecord->hasMethod($relName)) {
                $relObjTo = $this->toRecord->$relName();
                $toTitle = $this->getObjectDisplay($relObjTo);
            }

            if (!$this->fromRecord) {
                $diffed->setField(
                    $setField,
                    DBField::create_field('HTMLFragment', "<ins>{$toTitle}</ins>")
                );
            } elseif ($this->fromRecord->$relField != $this->toRecord->$relField) {
                $fromTitle = '';
                $relObjFrom = null;
                if ($this->fromRecord->hasMethod($relName)) {
                    $relObjFrom = $this->fromRecord->$relName();
                    $fromTitle = $this->getObjectDisplay($relObjFrom);
                }

                $diffTitle = Deprecation::withNoReplacement(function () use ($fromTitle, $toTitle) {
                    return Diff::compareHTML($fromTitle, $toTitle);
                });
                // Set the field.
                $diffed->setField(
                    $setField,
                    DBField::create_field('HTMLFragment', $diffTitle)
                );
            }
        }

        return $diffed;
    }

    /**
     * Get HTML to display for a dataobject
     *
     * @param DataObject $object
     * @return string HTML output
     */
    protected function getObjectDisplay($object = null)
    {
        if (!$object || !$object->isInDB()) {
            return '';
        }

        // Use image tag
        // TODO Use CMSThumbnail instead to limit max size, blocked by DataDifferencerTest and GC
        // not playing nice with mocked images
        if ($object instanceof Image) {
            return $object->getTag();
        }

        // Format title
        return $object->obj('Title')->forTemplate();
    }

    /**
     * Get a SS_List of the changed fields.
     * Each element is an array data containing
     *  - Name: The field name
     *  - Title: The human-readable field title
     *  - Diff: An HTML diff showing the changes
     *  - From: The older version of the field
     *  - To: The newer version of the field
     */
    public function ChangedFields()
    {
        $base = $this->fromRecord ?: $this->toRecord;
        $changedFields = ArrayList::create();

        foreach ($this->changedFieldNames() as $field) {
            // Only show HTML diffs for fields which allow HTML values in the first place
            $fieldObj = $this->toRecord->dbObject($field);
            if ($this->fromRecord) {
                $fromField = $this->fromRecord->$field;
                $toField = $this->toRecord->$field;
                $escapeHtml = (!$fieldObj || $fieldObj->config()->get('escape_type') != 'xml');
                $fieldDiff = Deprecation::withNoReplacement(function () use ($fromField, $toField, $escapeHtml) {
                    return Diff::compareHTML(
                        $fromField,
                        $toField,
                        $escapeHtml
                    );
                });
            } else {
                if ($fieldObj && $fieldObj->config()->get('escape_type') == 'xml') {
                    $fieldDiff = "<ins>" . $this->toRecord->$field . "</ins>";
                } else {
                    $fieldDiff = "<ins>" . Convert::raw2xml($this->toRecord->$field) . "</ins>";
                }
            }
            $changedFields->push(ArrayData::create([
                'Name' => $field,
                'Title' => $base->fieldLabel($field),
                'Diff' => $fieldDiff,
                'From' => $this->fromRecord ? $this->fromRecord->$field : null,
                'To' => $this->toRecord ? $this->toRecord->$field : null,
            ]));
        }

        return $changedFields;
    }

    /**
     * Get an array of the names of every fields that has changed.
     * This is simpler than {@link ChangedFields()}
     */
    public function changedFieldNames()
    {
        $base = $this->fromRecord ?: $this->toRecord;
        $fields = array_keys($base->toMap() ?? []);

        $changedFields = [];
        foreach ($fields as $field) {
            if (in_array($field, $this->ignoredFields ?? [])) {
                continue;
            }
            if (!$this->fromRecord || $this->fromRecord->$field != $this->toRecord->$field) {
                $changedFields[] = $field;
            }
        }

        return $changedFields;
    }
}
