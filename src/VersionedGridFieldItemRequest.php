<?php

namespace SilverStripe\Versioned;

use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;

/**
 * Provides versioned dataobject support to {@see GridFieldDetailForm_ItemRequest}
 *
 * @property GridFieldDetailForm_ItemRequest $owner
 */
class VersionedGridFieldItemRequest extends GridFieldDetailForm_ItemRequest
{

    protected function getFormActions()
    {
        // Check if record is versionable
        /** @var Versioned|DataObject $record */
        $record = $this->getRecord();
        if (!$record || !$record->has_extension(Versioned::class)) {
            return parent::getFormActions();
        }

        // Add extra actions prior to extensions so that these can be modified too
        $this->beforeExtending('updateFormActions', function (FieldList $actions) use ($record) {
            // Save & Publish action
            if ($record->canPublish()) {
                // "publish", as with "save", it supports an alternate state to show when action is needed.
                $publish = FormAction::create(
                    'doPublish',
                    _t(__CLASS__.'.BUTTONPUBLISH', 'Publish')
                )
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn btn-primary font-icon-rocket');

                // Insert after save
                if ($actions->fieldByName('action_doSave')) {
                    $actions->insertAfter('action_doSave', $publish);
                } else {
                    $actions->push($publish);
                }
            }

            // Unpublish action
            $isPublished = $record->isPublished();
            if ($isPublished && $record->canUnpublish()) {
                $actions->push(
                    FormAction::create(
                        'doUnpublish',
                        _t(__CLASS__.'.BUTTONUNPUBLISH', 'Unpublish')
                    )
                        ->setUseButtonTag(true)
                        ->setDescription(_t(
                            __CLASS__.'.BUTTONUNPUBLISHDESC',
                            'Remove this record from the published site'
                        ))
                        ->addExtraClass('btn-secondary')
                );
            }

            // Archive action
            if ($record->canArchive()) {
                // Replace "delete" action
                $actions->removeByName('action_doDelete');

                // "archive"
                $actions->push(
                    FormAction::create('doArchive', _t(__CLASS__.'.ARCHIVE', 'Archive'))
                        ->setDescription(_t(
                            __CLASS__.'.BUTTONARCHIVEDESC',
                            'Unpublish and send to archive'
                        ))
                        ->addExtraClass('delete btn-secondary')
                );
            }
        });

        return parent::getFormActions();
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
            'Archived {name} {title}',
            [
                'name' => $record->i18n_singular_name(),
                'title' => Convert::raw2xml($title)
            ]
        );
        $this->setFormMessage($form, $message);

        //when an item is deleted, redirect to the parent controller
        $controller = $this->getToplevelController();
        $controller->getRequest()->addHeader('X-Pjax', 'Content'); // Force a content refresh

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
        /** @var Versioned|DataObject $record */
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
            __CLASS__.'.Published',
            'Published {name} {link}',
            [
                'name' => $record->i18n_singular_name(),
                'link' => $link
            ]
        );
        $this->setFormMessage($form, $message);

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
            'Unpublished {name} {title}',
            [
                'name' => $record->i18n_singular_name(),
                'title' => Convert::raw2xml($title)
            ]
        );
        $this->setFormMessage($form, $message);

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
}
