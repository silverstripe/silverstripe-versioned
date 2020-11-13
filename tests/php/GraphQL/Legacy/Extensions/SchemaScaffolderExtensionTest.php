<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Legacy\Extensions;

use GraphQL\Type\Definition\ObjectType;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\SchemaScaffolder;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\GraphQL\Types\VersionedStage;
use SilverStripe\Versioned\GraphQL\Types\VersionSortType;
use SilverStripe\Versioned\Tests\VersionedTest\ChangeSetFake;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Tests\VersionedTest\UnversionedWithField;

class SchemaScaffolderExtensionTest extends SapphireTest
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

    public function testSchemaScaffolderEnsuresMemberType()
    {
        $manager = new Manager();
        $manager->addType((new VersionedStage())->toType());
        $manager->addType((new VersionSortType())->toType());
        $memberType = StaticSchema::inst()->typeNameForDataObject(Member::class);
        $this->assertFalse($manager->hasType($memberType));

        $scaffolder = new SchemaScaffolder();
        $scaffolder->type(Fake::class);
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
