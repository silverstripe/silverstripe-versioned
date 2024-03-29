<?php
namespace SilverStripe\Versioned\Tests\GraphQL\Plugins;

use GraphQL\Type\Definition\IntType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Config\ModelConfiguration;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\DataObjectScaffolder;
use SilverStripe\GraphQL\Schema\DataObject\DataObjectModel;
use SilverStripe\GraphQL\Schema\DataObject\ModelCreator;
use SilverStripe\GraphQL\Schema\DataObject\Resolver;
use SilverStripe\GraphQL\Schema\Field\Field;
use SilverStripe\GraphQL\Schema\Plugin\SortPlugin;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\SchemaConfig;
use SilverStripe\GraphQL\Schema\Type\ModelType;
use SilverStripe\GraphQL\Schema\Type\Type;
use SilverStripe\ORM\SS_List;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\GraphQL\Operations\ReadVersions;
use SilverStripe\Versioned\GraphQL\Plugins\VersionedDataObject;
use SilverStripe\Versioned\GraphQL\Resolvers\VersionedResolver;
use SilverStripe\Versioned\GraphQL\Types\VersionedStage;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Tests\VersionedTest\UnversionedWithField;
use SilverStripe\Versioned\Versioned;

// GraphQL dependency is optional in versioned,
// and the following implementation relies on existence of this class (in GraphQL v4)
if (!class_exists(Schema::class)) {
    return;
}

class VersionedDataObjectPluginTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        Fake::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Schema::class)) {
            $this->markTestSkipped('Skipped GraphQL 4 test ' . __CLASS__);
        }
    }

    public function testPluginAddsVersionedFields()
    {
        $config = $this->createSchemaConfig();
        $model = DataObjectModel::create(Fake::class, $config);
        $type = ModelType::create($model);
        $type->addField('name');

        $schema = new Schema('test', $config);
        $schema->addModel($type);
        $plugin = new VersionedDataObject();
        $plugin->updateSchema($schema);
        $this->assertInstanceOf(ModelType::class, $schema->getModelByClassName(Member::class));

        $plugin->apply($type, $schema);
        $storableSchema = $schema->createStoreableSchema();
        $types = $storableSchema->getTypes();
        $this->assertArrayHasKey('FakeVersion', $types);
        $versionType = $types['FakeVersion'];
        $this->assertInstanceOf(Type::class, $versionType);

        $fields = ['author', 'publisher', 'published', 'liveVersion', 'latestDraftVersion'];
        foreach ($fields as $fieldName) {
            $field = $versionType->getFieldByName($fieldName);
            $this->assertInstanceOf(Field::class, $field, 'Field ' . $fieldName . ' not found');
            $this->assertEquals(VersionedResolver::class . '::resolveVersionFields', $field->getResolver()->toString());
        }

        $fields = ['version', 'name'];
        foreach ($fields as $fieldName) {
            $field = $type->getFieldByName($fieldName);
            $this->assertInstanceOf(Field::class, $field, 'Field ' . $fieldName . ' not found');
            // temorarily removed until BuildState API is in graphql ^4
            //$this->assertEquals(Resolver::class . '::resolve', $field->getEncodedResolver()->getRef()->toString());
        }

        $this->assertInstanceOf(Field::class, $type->getFieldByName('versions'));
    }

    public function testPluginDoesntAddVersionedFieldsToUnversionedObjects()
    {
        Fake::remove_extension(Versioned::class);
        $config = $this->createSchemaConfig();
        $type = ModelType::create(DataObjectModel::create(Fake::class, $config));
        $type->addField('Name');

        $schema = new Schema('test', $config);
        $schema->addModel($type);
        $plugin = new VersionedDataObject();
        $plugin->updateSchema($schema);

        $plugin->apply($type, $schema);
        $type = $schema->getType('FakeVersion');
        $this->assertNull($type);

        Fake::add_extension(Versioned::class);
    }

    /**
     * @return SchemaConfig
     */
    private function createSchemaConfig(): SchemaConfig
    {
        return new SchemaConfig([
            'modelCreators' => [ModelCreator::class],
        ]);
    }
}
