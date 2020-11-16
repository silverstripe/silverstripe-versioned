<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Legacy\Operations;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use InvalidArgumentException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\GraphQL\Operations\Rollback;
use SilverStripe\Versioned\Tests\GraphQL\Fake\FakeDataObjectStub;

// GraphQL dependency is optional in versioned,
// and this legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(Manager::class)) {
    return;
}

class RollbackTest extends SapphireTest
{
    protected $usesDatabase = true;

    public static $extra_dataobjects = [
        FakeDataObjectStub::class,
    ];

    protected function setUp()
    {
        parent::setUp();
        if (class_exists(Schema::class)) {
            $this->markTestSkipped('Skipped GraphQL 3 test ' . __CLASS__);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Current user does not have permission to roll back this resource
     */
    public function testRollbackCannotBePerformedWithoutEditPermission()
    {
        // Create a fake version of our stub
        $stub = FakeDataObjectStub::create();
        $stub->Name = 'First';
        $stub->Editable = false;
        $stub->write();

        $this->doMutation($stub);
    }

    public function testRollbackRecursiveIsCalled()
    {
        // Create a fake version of our stub
        $stub = FakeDataObjectStub::create();
        $stub->Name = 'First';
        $stub->write();

        $this->doMutation($stub);

        $this->assertTrue($stub::$rollbackCalled, 'RollbackRecursive was called');
    }

    protected function doMutation(DataObject $stub, $toVersion = 1, $member = null)
    {
        if (!$stub->isInDB()) {
            $stub->write();
        }

        $stubClass = get_class($stub);
        $typeName = StaticSchema::inst()->typeNameForDataObject($stubClass);
        $manager = new Manager();
        $manager->addType(new ObjectType(['name' => $typeName]));

        $mutation = new Rollback($stubClass);
        $scaffold = $mutation->scaffold($manager);
        $this->assertInternalType('callable', $scaffold['resolve'], 'Resolve function is scaffolded correctly');

        $args = [
            'ID' => $stub->ID,
            'ToVersion' => $toVersion,
        ];

        $scaffold['resolve'](
            null,
            $args,
            [ 'currentUser' => $member ?: Security::getCurrentUser() ],
            new ResolveInfo([])
        );
    }
}
