<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Tests\VersionedOwnershipTest\UnversionedOwner;
use SilverStripe\Versioned\Tests\VersionedTest\TestObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Versioned\VersionedGridFieldItemRequest;

class VersionedGridFieldItemRequestTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        TestObject::class,
    ];

    public function testItSetsPublishedButtonsForVersionedOwners()
    {
        $itemRequest = $this->createItemRequestForObject(new TestObject());
        $this->logInWithPermission('ADMIN');
        $form = $itemRequest->ItemEditForm();
        $actions = $form->Actions();
        $this->assertInstanceOf(FormAction::class, $actions->fieldByName('action_doPublish'));
    }

    public function testItDoesNotSetPublishedButtonsForUnversionedOwners()
    {
        TestObject::remove_extension(Versioned::class);
        $itemRequest = $this->createItemRequestForObject(new TestObject());
        $this->logInWithPermission('ADMIN');
        $form = $itemRequest->ItemEditForm();
        $actions = $form->Actions();
        $this->assertNull($actions->fieldByName('action_doPublish'));
        $this->assertInstanceOf(FormAction::class, $actions->fieldByName('action_doSave'));
    }

    public function testItDisplaysAWarningWhenUnversionedOwnerOwnsVersioned()
    {
        TestObject::remove_extension(Versioned::class);
        $itemRequest = $this->createItemRequestForObject(new UnversionedOwner());
        $this->logInWithPermission('ADMIN');
        $form = $itemRequest->ItemEditForm();
        $actions = $form->Actions();
        $this->assertNull($actions->fieldByName('action_doPublish'));
        $this->assertInstanceOf(FormAction::class, $actions->fieldByName('action_doSave'));
        $this->assertInstanceOf(LiteralField::class, $actions->fieldByName('warning'));
        $this->assertRegExp('/will be published/', $actions->fieldByName('warning')->getContent());
    }

    protected function createItemRequestForObject(DataObject $obj)
    {
        return new VersionedGridFieldItemRequest(
            new GridField('test', 'test', new ArrayList()),
            new GridFieldDetailForm(),
            $obj,
            new RequestHandler(),
            'test'
        );
    }
}
