<?php
namespace SilverStripe\Versioned\Tests\GraphQL\Fake;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class FakeDataObjectStub extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedGraphQLTest_DataObject_RollbackStub';

    private static $db = [
        'Name' => 'Varchar',
        'Editable' => 'Boolean',
    ];

    private static $defaults = [
        'Editable' => true,
    ];

    private static $extensions = [
        Versioned::class,
    ];

    public static $rollbackCalled = false;

    public function canEdit($member = null)
    {
        return $this->Editable;
    }

    public function rollbackRecursive($rollbackVersion)
    {
        FakeDataObjectStub::$rollbackCalled = true;
    }
}
