<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Legacy\Plugins;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use InvalidArgumentException;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Resolvers\ApplyVersionFilters;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\ReadOne;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\Schema\DataObject\DataObjectModel;
use SilverStripe\GraphQL\Schema\Field\ModelQuery;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\GraphQL\Schema\Type\ModelType;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\GraphQL\Plugins\VersionedDataObject;
use SilverStripe\Versioned\GraphQL\Plugins\VersionedRead;
use SilverStripe\Versioned\GraphQL\Resolvers\VersionedResolver;
use SilverStripe\Versioned\GraphQL\Types\VersionedInputType;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Versioned;

class VersionedReadTest extends SapphireTest
{
    protected function setUp()
    {
        parent::setUp();
        if (!class_exists(Schema::class)) {
            $this->markTestSkipped('Skipped GraphQL 4 test ' . __CLASS__);
        }
    }

    public function testVersionedRead()
    {
        $model = DataObjectModel::create(Fake::class);
        $query = ModelQuery::create($model, 'testQuery');
        $schema = new Schema('test');
        $plugin = new VersionedRead();
        $plugin->apply($query, $schema);
        $this->assertCount(1, $query->getResolverAfterwares());
        $this->assertEquals(
            VersionedResolver::class . '::resolveVersionedRead',
            $query->getResolverAfterwares()[0]->getRef()->toString()
        );
        $this->assertCount(1, $query->getArgs());
        $this->assertEquals('versioning', $query->getArgs()[0]);
    }
}
