<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Legacy\Extensions;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Resolvers\ApplyVersionFilters;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Read;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\GraphQL\Types\VersionedInputType;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Core\Injector\Injector;

class ReadExtensionTest extends SapphireTest
{

    public static $extra_dataobjects = [
        Fake::class,
    ];

    protected function setUp()
    {
        parent::setUp();
        if (class_exists(Schema::class)) {
            $this->markTestSkipped('Skipped GraphQL 3 test ' . __CLASS__);
        }
    }

    public function testReadExtensionAppliesFilters()
    {
        $mock = $this->getMockBuilder(ApplyVersionFilters::class)
            ->setMethods(['applyToList'])
            ->getMock();
        $mock
            ->expects($this->once())
            ->method('applyToList');

        Injector::inst()->registerService($mock, ApplyVersionFilters::class);

        $manager = new Manager();
        $manager->addType((new VersionedInputType())->toType());
        $manager->addType(new ObjectType(['name' => StaticSchema::inst()->typeNameForDataObject(Fake::class)]));
        $read = new Read(Fake::class);
        $read->setUsePagination(false);
        $readScaffold = $read->scaffold($manager);
        $this->assertInternalType('callable', $readScaffold['resolve']);
        $readScaffold['resolve'](null, ['Versioning' => true], ['currentUser' => new Member()], new ResolveInfo([]));
    }
}
