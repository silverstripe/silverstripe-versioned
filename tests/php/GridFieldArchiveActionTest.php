<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Versioned\GridFieldArchiveAction;
use SilverStripe\Versioned\Tests\ChangeSetTest\UnversionedObject;
use SilverStripe\Versioned\Tests\VersionedTest\TestObject;
use SilverStripe\Versioned\Versioned;

class GridFieldArchiveActionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TestObject::class,
    ];

    protected function setUp()
    {
        parent::setUp();
        $this->logInWithPermission('ADMIN');
    }

    public function testItGetsATitle()
    {
        $action = new GridFieldArchiveAction();
        $record = new TestObject();
        $grid = $this->createGridField();


        $this->assertEquals(
            $action->getTitle($grid, $record, null),
            $action->getArchiveAction($grid, $record)->getAttribute('title')
        );

        $this->assertEquals('Delete', $action->getTitle($grid, new UnversionedObject(), null));
    }

    public function testGroup()
    {
        $action = new GridFieldArchiveAction();
        $record = new TestObject();
        $grid = $this->createGridField();

        $this->assertEquals(
            GridField_ActionMenuItem::DEFAULT_GROUP,
            $action->getGroup($grid, $record, null)
        );

        $record = new UnversionedObject();

        $this->assertNull($action->getGroup($grid, $record, null));
    }

    public function testExtraData()
    {
        $action = new GridFieldArchiveAction();
        $record = new TestObject();
        $grid = $this->createGridField();

        $this->assertEquals(
            $action->getArchiveAction($grid, $record)->getAttributes(),
            $action->getExtraData($grid, $record, null)
        );

        $record = new UnversionedObject();

        $this->assertNull($action->getExtraData($grid, $record, null));
    }

    public function testAugmentColumns()
    {
        $action = new GridFieldArchiveAction();
        $grid = $this->createGridField();
        $grid->setModelClass(TestObject::class);
        $grid->getConfig()->addComponent(new GridFieldDeleteAction());
        $columns = ['TestCol'];
        $action->augmentColumns($grid, $columns);
        $this->assertCount(2, $columns);
        $this->assertContains('Actions', $columns);
        $this->assertNull($grid->getConfig()->getComponentByType(GridFieldDeleteAction::class));

        $grid = $this->createGridField();
        $grid->setModelClass(UnversionedObject::class);
        $grid->getConfig()->addComponent(new GridFieldDeleteAction());
        $columns = ['TestCol'];
        $action->augmentColumns($grid, $columns);
        $this->assertCount(2, $columns);
        $this->assertContains('Actions', $columns);
        $this->assertInstanceOf(
            GridFieldDeleteAction::class,
            $grid->getConfig()->getComponentByType(GridFieldDeleteAction::class)
        );
    }

    public function testColumnAttributes()
    {
        $action = new GridFieldArchiveAction();
        $result = $action->getColumnAttributes($this->createGridField(), new TestObject(), null);
        $this->assertArrayHasKey('class', $result);
        $this->assertEquals('grid-field__col-compact', $result['class']);
    }

    public function testColumnMetadata()
    {
        $action = new GridFieldArchiveAction();
        $result = $action->getColumnMetadata($this->createGridField(), 'Actions');
        $this->assertArrayHasKey('title', $result);
    }

    public function testColumnsHandled()
    {
        $action = new GridFieldArchiveAction();
        $grid = $this->createGridField();
        $this->assertCount(1, $action->getColumnsHandled($grid));
        $this->assertContains('Actions', $action->getColumnsHandled($grid));
    }

    public function testActions()
    {
        $action = new GridFieldArchiveAction();
        $grid = $this->createGridField();
        $this->assertCount(1, $action->getActions($grid));
        $this->assertContains('archiverecord', $action->getActions($grid));
    }

    public function testColumnContent()
    {
        $action = new GridFieldArchiveAction();
        $record = new TestObject();
        $grid = $this->createGridField();
        $content = $action->getColumnContent($grid, $record, null);
        $this->assertInstanceOf(DBHTMLText::class, $content);
        $record = new UnversionedObject();

        $this->assertNull($action->getColumnContent($grid, $record, null));
    }

    public function testHandleAction()
    {
        /* @var DataObject|Versioned $record */
        $record = new TestObject();
        $record->write();
        $record->publishRecursive();
        $grid = $this->createGridField();
        $grid->setList(TestObject::get());

        $action = new GridFieldArchiveAction();
        $this->assertFalse($record->isArchived());
        $action->handleAction(
            $grid,
            'archiverecord',
            ['RecordID' => $record->ID],
            []
        );

        $record = TestObject::get()
            ->setDataQueryParam("Versioned.mode", "latest_versions")
            ->byID($record->ID);
        $this->assertNotNull($record);
        $this->assertTrue($record->isArchived());
    }

    public function testGetArchiveAction()
    {
        $action = new GridFieldArchiveAction();
        $grid = $this->createGridField();

        $this->assertInstanceOf(
            GridField_FormAction::class,
            $action->getArchiveAction($grid, new TestObject())
        );

        $this->assertNull(
            $action->getArchiveAction($grid, new UnversionedObject())
        );
    }

    /**
     * @return GridField
     */
    protected function createGridField()
    {
        $mock = $this->getMockBuilder(Controller::class)
            ->setMethods(['Link'])
            ->getMock();
        $mock->method('Link')->will($this->returnValue('Test'));
        $form = new Form(
            $mock,
            'TestForm',
            FieldList::create(GridField::create('Test')),
            FieldList::create()
        );

        return $form->Fields()->dataFieldByName('Test');
    }
}
