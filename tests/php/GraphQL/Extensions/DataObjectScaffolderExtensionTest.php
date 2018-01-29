<?php
namespace SilverStripe\Versioned\Tests\GraphQL\Extensions;

use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\ObjectType;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\DataObjectScaffolder;
use SilverStripe\Versioned\GraphQL\Types\VersionedStage;
use SilverStripe\Versioned\Tests\VersionedTest\AnotherSubclass;
use SilverStripe\Versioned\Tests\VersionedTest\ChangeSetTestObject;
use SilverStripe\Versioned\Tests\VersionedTest\PublicViaExtension;
use SilverStripe\Versioned\Tests\VersionedTest\SingleStage;
use SilverStripe\Versioned\Tests\VersionedTest\TestObject;
use SilverStripe\Versioned\Tests\VersionedTest\PublicStage;
use SilverStripe\Versioned\Tests\VersionedTest\UnversionedWithField;
use SilverStripe\Versioned\Versioned;

class DataObjectScaffolderExtensionTest extends SapphireTest
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

    public function testDataObjectScaffolderAddsVersionedFields()
    {
        $manager = new Manager();
        $manager->addType((new VersionedStage())->toType());
        $scaffolder = new DataObjectScaffolder(TestObject::class);
        $scaffolder->addFields(['Name', 'Title']);
        $scaffolder->addToManager($manager);
        $typeName = $scaffolder->typeName();

        $type = $manager->getType($typeName);
        $this->assertInstanceOf(ObjectType::class, $type);
        $fields = $type->config['fields']();
        $this->assertArrayHasKey('Version', $fields);
        $this->assertArrayHasKey('Versions', $fields);
        $this->assertInstanceOf(IntType::class, $fields['Version']['type']);
        $this->assertInstanceOf(ObjectType::class, $fields['Versions']['type']);
    }

    public function testDataObjectScaffolderDoesntAddVersionedFieldsToUnversionedObjects()
    {
        TestObject::remove_extension(Versioned::class);
        $manager = new Manager();
        $manager->addType((new VersionedStage())->toType());
        $scaffolder = new DataObjectScaffolder(UnversionedWithField::class);
        $scaffolder->addToManager($manager);
        $typeName = $scaffolder->typeName();

        $type = $manager->getType($typeName);
        $this->assertInstanceOf(ObjectType::class, $type);
        $fields = $type->config['fields']();
        $this->assertArrayNotHasKey('Version', $fields);
        $this->assertArrayNotHasKey('Versions', $fields);
    }

}