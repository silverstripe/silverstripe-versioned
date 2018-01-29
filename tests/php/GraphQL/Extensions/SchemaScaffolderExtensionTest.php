<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Extensions;

use GraphQL\Type\Definition\ObjectType;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\DataObjectScaffolder;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\GraphQL\Scaffolding\Util\ScaffoldingUtil;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\GraphQL\Types\VersionedStage;
use SilverStripe\Versioned\Tests\VersionedTest\AnotherSubclass;
use SilverStripe\Versioned\Tests\VersionedTest\ChangeSetTestObject;
use SilverStripe\Versioned\Tests\VersionedTest\PublicViaExtension;
use SilverStripe\Versioned\Tests\VersionedTest\SingleStage;
use SilverStripe\Versioned\Tests\VersionedTest\TestObject;
use SilverStripe\Versioned\Tests\VersionedTest\PublicStage;
use SilverStripe\Versioned\Tests\VersionedTest\UnversionedWithField;

class SchemaScaffolderExtensionTest extends SapphireTest
{
    protected static $fixture_file = '../../VersionedTest.yml';

    public static $extra_dataobjects = [
        TestObject::class,
        PublicStage::class,
        PublicViaExtension::class,
        AnotherSubclass::class,
        SingleStage::class,
        ChangeSetTestObject::class,
    ];

    public function testSchemaScaffolderEnsuresMemberType()
    {
        $manager = new Manager();
        $manager->addType((new VersionedStage())->toType());
        $memberType = ScaffoldingUtil::typeNameForDataObject(Member::class);
        $this->assertFalse($manager->hasType($memberType));

        $scaffolder = new SchemaScaffolder();
        $scaffolder->type(TestObject::class);
        $scaffolder->addToManager($manager);

        $this->assertTrue($manager->hasType($memberType));
        $this->assertInstanceOf(ObjectType::class, $manager->getType($memberType));

        $manager = new Manager();
        $this->assertFalse($manager->hasType($memberType));

        $scaffolder = new SchemaScaffolder();
        $scaffolder->type(UnversionedWithField::class);
        $scaffolder->addToManager($manager);
        $this->assertFalse($manager->hasType($memberType));
    }

}