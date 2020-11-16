<?php
namespace SilverStripe\Versioned\Tests\GraphQL\Legacy\Extensions;

use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\ObjectType;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\DataObjectScaffolder;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\Versioned\GraphQL\Types\VersionedStage;
use SilverStripe\Versioned\GraphQL\Types\VersionSortType;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Tests\VersionedTest\UnversionedWithField;
use SilverStripe\Versioned\Versioned;

// GraphQL dependency is optional in versioned,
// and this legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(Manager::class)) {
    return;
}

class DataObjectScaffolderExtensionTest extends SapphireTest
{
    public static $extra_dataobjects = [
        Fake::class,
    ];

    protected function setUp()
    {
        parent::setUp();
        if (!class_exists(Manager::class)) {
            $this->markTestSkipped('Skipped GraphQL 3 test ' . __CLASS__);
        }
    }

    public function testDataObjectScaffolderAddsVersionedFields()
    {
        $manager = new Manager();
        $manager->addType((new VersionedStage())->toType());
        $manager->addType((new VersionSortType())->toType());
        $scaffolder = new DataObjectScaffolder(Fake::class);
        $scaffolder->addFields(['Name', 'Title']);
        $scaffolder->addToManager($manager);
        $typeName = $scaffolder->getTypeName();

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
        Fake::remove_extension(Versioned::class);
        $manager = new Manager();
        $manager->addType((new VersionedStage())->toType());
        $scaffolder = new DataObjectScaffolder(UnversionedWithField::class);
        $scaffolder->addToManager($manager);
        $typeName = $scaffolder->getTypeName();

        $type = $manager->getType($typeName);
        $this->assertInstanceOf(ObjectType::class, $type);
        $fields = $type->config['fields']();
        $this->assertArrayNotHasKey('Version', $fields);
        $this->assertArrayNotHasKey('Versions', $fields);
    }
}
