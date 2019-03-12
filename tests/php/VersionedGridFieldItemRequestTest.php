<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Tests\VersionedGridFieldItemRequestTest\UnversionedObject;
use SilverStripe\Versioned\Tests\VersionedGridFieldItemRequestTest\UnversionedOwner;
use SilverStripe\Versioned\Tests\VersionedGridFieldItemRequestTest\VersionedObject;
use SilverStripe\Versioned\Tests\VersionedGridFieldItemRequestTest\VersionedOwner;
use SilverStripe\Versioned\Tests\VersionedGridFieldTest\TestController;
use SilverStripe\Versioned\VersionedGridFieldItemRequest;

class VersionedGridFieldItemRequestTest extends SapphireTest
{
    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        VersionedObject::class,
        VersionedOwner::class,
        UnversionedOwner::class,
        UnversionedObject::class,
    ];

    /**
     * @var string
     */
    protected static $fixture_file = 'VersionedGridFieldItemRequestTest.yml';

    public function testItSetsPublishedButtonsForVersionedOwners()
    {
        $testObject = $this->objFromFixture(VersionedObject::class, 'object-1');
        $itemRequest = $this->createItemRequestForObject($testObject);
        $this->logInWithPermission('ADMIN');
        $form = $itemRequest->ItemEditForm();
        $actions = $form->Actions();

        $this->assertInstanceOf(
            FormAction::class,
            $actions->fieldByName('MajorActions')->fieldByName('action_doPublish')
        );
    }

    public function testActionsUnversionedOwner()
    {
        $itemRequest = $this->createItemRequestForObject(UnversionedOwner::create());
        $this->logInWithPermission('ADMIN');
        $form = $itemRequest->ItemEditForm();
        $actions = $form->Actions();

        // No publish action
        $this->assertNull($actions->fieldByName('action_doPublish'));
        $this->assertInstanceOf(
            FormAction::class,
            $actions->fieldByName('MajorActions')->fieldByName('action_doSave')
        );

        // No warning for new items
        $this->assertNull($actions->fieldByName('warning'));
    }

    /**
     * Ensure owned objects warn on unpublish
     */
    public function testActionsVersionedOwned()
    {
        // Object to edit
        $object = $this->objFromFixture(VersionedObject::class, 'object-1');
        $object->publishSingle();

        // 4 owners, 3 published owners
        for ($i = 1; $i <= 4; $i++) {
            $owner = VersionedOwner::create();
            $owner->RelatedID = $object->ID;
            $owner->Title = "My Owner {$i}";
            $owner->write();
            // Only 3 of these are published
            if ($i !== 4) {
                $owner->publishSingle();
            }
        }

        $itemRequest = $this->createItemRequestForObject($object);
        $this->logInWithPermission('ADMIN');
        $form = $itemRequest->ItemEditForm();
        $actions = $form->Actions();

        // Get unpublish action
        $unpublishAction = $actions
            ->fieldByName('ActionMenus')
            ->fieldByName('MoreOptions')
            ->fieldByName('action_doUnpublish');

        $this->assertInstanceOf(FormAction::class, $unpublishAction);
        $this->assertEquals(3, $unpublishAction->getAttribute('data-owners'));
    }

    /**
     * A save on a versioned object won't trigger a publish
     */
    public function testVersionedObjectsNotPublished()
    {
        $newObject = VersionedObject::create();
        $itemRequest = $this->createItemRequestForObject($newObject);
        $this->logInWithPermission('ADMIN');

        $form = $itemRequest->ItemEditForm();
        $form->loadDataFrom($data = ['Title' => 'New Object']);
        $itemRequest->doSave($data, $form);

        $this->assertTrue($newObject->isInDB());
        $this->assertTrue($newObject->isOnDraftOnly());
    }

    /**
     * A save on an unversioned object triggers publishing
     */
    public function testUnversionedObjectsPublishChildren()
    {
        $newChild = VersionedObject::create();
        $newChild->Title = 'New child versioned';
        $newChild->write();
        $newObject = UnversionedOwner::create();
        $newObject->RelatedID = $newChild->ID;
        $newObject->write();

        // Update this
        $itemRequest = $this->createItemRequestForObject($newObject);
        $this->logInWithPermission('ADMIN');
        $form = $itemRequest->ItemEditForm();

        $form->loadDataFrom($newObject);
        $itemRequest->doSave($newObject->toMap(), $form);

        // Nested child is published
        $this->assertTrue($newChild->isPublished());

        /** @var LiteralField $warningField */
        $actions = $form->Actions();
        $warningField = $actions->fieldByName('warning');

        // Warning was removed as part of #154 ... it may be brough back later
        $this->assertNull($warningField);
    }

    protected function createItemRequestForObject(DataObject $obj)
    {
        $controller = TestController::create();
        $parentForm = Form::create($controller, 'Form', FieldList::create(), FieldList::create());
        return VersionedGridFieldItemRequest::create(
            GridField::create('test', 'test', ArrayList::create())
                ->setForm($parentForm),
            new GridFieldDetailForm(),
            $obj,
            $controller,
            'test'
        );
    }
}
