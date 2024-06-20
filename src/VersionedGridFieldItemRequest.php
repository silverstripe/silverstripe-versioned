<?php

namespace SilverStripe\Versioned;

use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

/**
 * Provides versioned dataobject support to {@see GridFieldDetailForm_ItemRequest}
 *
 * @property GridFieldDetailForm_ItemRequest $owner
 */
class VersionedGridFieldItemRequest extends GridFieldDetailForm_ItemRequest
{
    public function Breadcrumbs($unlinked = false)
    {
        $items = parent::Breadcrumbs($unlinked);
        $status = $this->getRecordStatus();
        $badge = null;
        if ($status) {
            // Generate badge
            $badge = DBField::create_field('HTMLFragment', sprintf(
                '<span class="badge version-status version-status--%s">%s</span>',
                $status['class'],
                $status['title']
            ));
        }
        $this->extend('updateBadge', $badge);

        if ($badge) {
            $lastItem = $items->last();
            $lastItem->setField('Extra', $badge);
        }

        return $items;
    }

    /**
     * @return FieldList
     */
    protected function getFormActions()
    {
        // Check if record is versionable
        /** @var Versioned|RecursivePublishable|DataObject $record */
        $record = $this->getRecord();
        $ownerIsStaged = $record
            && $record->hasExtension(Versioned::class)
            && $record->hasStages();
        $ownerRecursivePublishes = !$ownerIsStaged
            && $record
            && $record->hasExtension(RecursivePublishable::class)
            && $record->config()->get('owns');

        // Add extra actions prior to extensions so that these can be modified too
        if ($ownerIsStaged) {
            $this->beforeExtending(
                'updateFormActions',
                function (FieldList $actions) use ($record, $ownerIsStaged) {
                    $this->addVersionedButtons($record, $actions);
                }
            );
        } elseif ($ownerRecursivePublishes) {
            $this->beforeExtending(
                'updateFormActions',
                function (FieldList $actions) use ($record, $ownerIsStaged) {
                    $this->addUnversionedButtons($record, $actions);
                }
            );
        }

        return parent::getFormActions();
    }

    /**
     * If a record is recursive publishable, but not versioned, all saves should trigger a recursive publish.
     *
     * @param array $data
     * @param Form $form
     * @return DataObject $record
     */
    public function saveFormIntoRecord($data, $form)
    {
        $record = parent::saveFormIntoRecord($data, $form);

        // Note: Don't publish if versioned, since that's a separate action
        $ownerIsVersioned = $record && $record->hasExtension(Versioned::class);
        $ownerIsPublishable = $record && $record->hasExtension(RecursivePublishable::class);
        if ($ownerIsPublishable && !$ownerIsVersioned) {
            /** @var RecursivePublishable $record */
            $record->publishRecursive();
        }

        return $record;
    }

    /**
     * Archive this versioned record
     *
     * @param array $data
     * @param Form $form
     * @return HTTPResponse
     */
    public function doArchive($data, $form)
    {
        /** @var Versioned|DataObject $record */
        $record = $this->getRecord();
        if (!$record->canArchive()) {
            return $this->httpError(403);
        }

        // Record name before it's deleted
        $title = $record->Title;
        $record->doArchive();

        $message = _t(
            __CLASS__ . '.Archived',
            'Archived {name} "{title}"',
            [
                'name' => $record->i18n_singular_name(),
                'title' => $title
            ]
        );
        $this->setFormMessage($form, $message);

        //when an item is deleted, redirect to the parent controller
        $controller = $this->getToplevelController();
        $controller->getRequest()->addHeader('X-Pjax', 'Content'); // Force a content refresh
        $controller->getResponse()->addHeader('X-Status', rawurlencode($message));
        return $controller->redirect($this->getBackLink(), 302); //redirect back to admin section
    }

    /**
     * Publish this versioned record
     *
     * @param array $data
     * @param Form $form
     * @return HTTPResponse
     */
    public function doPublish($data, $form)
    {
        /** @var Versioned|RecursivePublishable|DataObject $record */
        $record = $this->getRecord();
        $isNewRecord = $record->ID == 0;

        // Check permission
        if (!$record->canPublish()) {
            return $this->httpError(403);
        }

        // Initial save and reload
        $record = $this->saveFormIntoRecord($data, $form);
        $record->publishRecursive();

        $editURL = $this->Link('edit');
        $xmlTitle = Convert::raw2xml($record->Title);
        $link = "<a href=\"{$editURL}\">{$xmlTitle}</a>";
        $message = _t(
            __CLASS__ . '.Published',
            'Published {name} {link}',
            [
                'name' => $record->i18n_singular_name(),
                'link' => $link
            ]
        );
        $this->setFormMessage($form, $message);

        $message = _t(
            __CLASS__ . '.PUBLISHEDTOASTMESSAGE',
            'Published {type} "{title}"',
            [
                'type' => $record->i18n_singular_name(),
                'title' => $record->Title
            ]
        );

        $controller = $this->getToplevelController();
        $controller->getResponse()->addHeader('X-Status', rawurlencode($message));

        return $this->redirectAfterSave($isNewRecord);
    }

    /**
     * Delete this record from the live site
     *
     * @param array $data
     * @param Form $form
     * @return HTTPResponse
     */
    public function doUnpublish($data, $form)
    {
        /** @var Versioned|DataObject $record */
        $record = $this->getRecord();
        if (!$record->canUnpublish()) {
            return $this->httpError(403);
        }

        // Record name before it's deleted
        $title = $record->Title;
        $record->doUnpublish();

        $message = _t(
            __CLASS__ . '.Unpublished',
            'Unpublished {name} "{title}"',
            [
                'name' => $record->i18n_singular_name(),
                'title' => $title
            ]
        );
        $this->setFormMessage($form, $message);

        $controller = $this->getToplevelController();
        $controller->getResponse()->addHeader('X-Status', rawurlencode($message));

        // Redirect back to edit
        return $this->redirectAfterSave(false);
    }

    /**
     * @param Form $form
     * @param string $message
     */
    protected function setFormMessage($form, $message)
    {
        $form->sessionMessage($message, 'good', ValidationResult::CAST_HTML);
        $controller = $this->getToplevelController();
        if ($controller->hasMethod('getEditForm')) {
            /** @var Form $backForm */
            $backForm = $controller->getEditForm();
            $backForm->sessionMessage($message, 'good', ValidationResult::CAST_HTML);
        }
    }

    /**
     * Return list of class / title to add on the end of record status in breadcrumbs
     *
     * @return array|null
     */
    protected function getRecordStatus()
    {
        /** @var DataObject|Versioned $record */
        $record = $this->record;

        // No status if un-versioned
        if (!$this->record->hasExtension(Versioned::class)) {
            return null;
        }

        if ($record->isOnDraftOnly()) {
            return [
                'class' => 'addedtodraft',
                'title' => _t(__CLASS__ . '.DRAFT', 'Draft')
            ];
        }

        if ($record->isModifiedOnDraft()) {
            return [
                'class' => 'modified',
                'title' => _t(__CLASS__ . '.MODIFIED', 'Modified')
            ];
        }

        return null;
    }

    /**
     * Getting buttons that are for versioned objects
     *
     * @param DataObject|Versioned|RecursivePublishable $record
     * @param FieldList $actions
     */
    protected function addVersionedButtons(DataObject $record, FieldList $actions)
    {
        // Get status of the object
        $isOnDraft = $record->isOnDraft();
        $isPublished = $record->isPublished();
        $stagesDiffer = $record->stagesDiffer();

        // Check permissions
        $canPublish = $record->canPublish();
        $canUnpublish = $record->canUnpublish();
        $canEdit = $record->canEdit();
        $canArchive = $record->canArchive();

        // "save", supports an alternate state that is still clickable, but notifies the user that the action is not needed.
        $noChangesClasses = 'btn-outline-primary font-icon-tick';

        /** @var DataObject|Versioned|RecursivePublishable $liveRecord */
        $liveRecord = Versioned::get_by_stage(get_class($record), Versioned::LIVE)->byID($record->ID);

        $majorActions = $actions->fieldByName('MajorActions');

        // "more options" action
        $rootTabSet = TabSet::create('ActionMenus');
        $moreOptions = Tab::create(
            'MoreOptions',
            _t(VersionedGridFieldItemRequest::class . '.MoreOptions', 'More options', 'Expands a view for more buttons')
        );

        $moreOptions->addExtraClass('popover-actions-simulate');
        $rootTabSet->push($moreOptions);
        $rootTabSet->addExtraClass('ss-ui-action-tabset action-menus noborder');

        $infoTemplate = SSViewer::get_templates_by_class(get_class($record), '_Information', DataObject::class);
        $moreOptions->push(
            LiteralField::create(
                'Information',
                $record->customise([
                    'Live' => $liveRecord,
                    'ExistsOnLive' => $isPublished
                ])->renderWith($infoTemplate)
            )
        );

        // "save" action
        $actionSave = ($majorActions) ? $majorActions->fieldByName('action_doSave') : null;
        if ($canEdit && $isOnDraft && $actionSave !== null) {
            $actionSave
                ->setTitle(_t(VersionedGridFieldItemRequest::class . '.BUTTONSAVED', 'Saved'))
                ->removeExtraClass('btn-primary font-icon-save font-icon-rocket')
                ->addExtraClass('btn-outline-primary font-icon-tick')
                ->setAttribute('data-btn-alternate-add', 'btn-primary font-icon-save')
                ->setAttribute('data-btn-alternate-remove', 'btn-outline-primary font-icon-tick')
                ->setAttribute('data-text-alternate', _t(CMSMain::class . '.SAVEDRAFT', 'Save draft'));
        }

        // "publish" action
        if ($canPublish && $isOnDraft) {
            // "publish", as with "save", it supports an alternate state to show when action is needed.
            $actionPublish = FormAction::create('doPublish', _t(VersionedGridFieldItemRequest::class . '.BUTTONPUBLISHED', 'Published'))
                ->addExtraClass($noChangesClasses)
                ->setAttribute('data-btn-alternate-add', 'btn-primary font-icon-rocket')
                ->setAttribute('data-btn-alternate-remove', $noChangesClasses)
                ->setUseButtonTag(true)
                ->setAttribute('data-text-alternate', _t(VersionedGridFieldItemRequest::class . '.BUTTONSAVEPUBLISH', 'Publish'));

            $actions->insertAfter('action_doSave', $actionPublish);

            // Set up the initial state of the button to reflect the state of the underlying record object.
            if ($stagesDiffer) {
                $actionPublish->addExtraClass('btn-primary font-icon-rocket');
                $actionPublish->setTitle(_t(VersionedGridFieldItemRequest::class . '.BUTTONSAVEPUBLISH', 'Publish'));
                $actionPublish->removeExtraClass($noChangesClasses);
            }
        }

        // "unpublish" action
        if ($isPublished && $isOnDraft && $canUnpublish) {
            $actionUnpublish = FormAction::create(
                'doUnpublish',
                _t(VersionedGridFieldItemRequest::class . '.BUTTONUNPUBLISH', 'Unpublish')
            )
                ->setDescription(
                    _t(VersionedGridFieldItemRequest::class . '.BUTTONUNPUBLISHDESC', 'Remove this record from the published site')
                )
                ->addExtraClass('btn-secondary');

            $moreOptions->push($actionUnpublish);

            if ($liveRecord) {
                $liveOwners = $liveRecord->findOwners(false)->count();
                $actionUnpublish->setAttribute('data-owners', $liveOwners);
            }
        }

        // "archive" action
        if (($isOnDraft || $isPublished) && $canArchive) {
            // Replace "delete" action
            $actions->removeByName('action_doDelete');
            $title = $isPublished
                ? _t(CMSMain::class . '.UNPUBLISH_AND_ARCHIVE', 'Unpublish and archive')
                : _t(CMSMain::class . '.ARCHIVE', 'Archive');

            $actionArchive = FormAction::create('doArchive', $title)
                ->addExtraClass('delete btn btn-secondary')
                ->setDescription(_t(VersionedGridFieldItemRequest::class . '.BUTTONARCHIVEDESC', 'Unpublish and send to archive'));

            $moreOptions->push($actionArchive);
        }

        $actions->insertAfter('MajorActions', $rootTabSet);
    }

    /**
     * Getting buttons that are for unversioned objects
     *
     * @param DataObject $record
     * @param FieldList $actions
     */
    protected function addUnversionedButtons(DataObject $record, FieldList $actions)
    {
        if (!$record->canEdit()) {
            return;
        }
        $saveAction = $actions->fieldByName('action_doSave');
        if (!$saveAction) {
            return;
        }
        if (!$this->record->ID) {
            return;
        }
        $saveAction->setTitle(_t(
            __CLASS__ . '.BUTTONAPPLYCHANGES',
            'Apply changes'
        ))->addExtraClass('btn-primary font-icon-save');
    }
}
