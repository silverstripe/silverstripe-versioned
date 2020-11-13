<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Legacy\Operations;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\GraphQL\Schema\Schema;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\GraphQL\Operations\Unpublish;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Versioned;
use Exception;

class UnpublishTest extends SapphireTest
{

    protected $usesDatabase = true;

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

    public function testPublish()
    {
        $typeName = StaticSchema::inst()->typeNameForDataObject(Fake::class);
        $manager = new Manager();
        $manager->addType(new ObjectType(['name' => $typeName]));

        $publish = new Unpublish(Fake::class);
        $scaffold = $publish->scaffold($manager);
        $this->assertInternalType('callable', $scaffold['resolve']);

        $record = new Fake();
        $record->Name = 'First';
        $record->write();
        $record->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        $result = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);

        $this->assertNotNull($result);
        $this->assertInstanceOf(Fake::class, $result);
        $this->assertEquals('First', $result->Name);

        $this->logInWithPermission('ADMIN');
        $member = Security::getCurrentUser();
        $scaffold['resolve'](
            null,
            [
                'ID' => $record->ID
            ],
            [ 'currentUser' => $member ],
            new ResolveInfo([])
        );
        $result = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);

        $this->assertNull($result);

        $record->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        $this->expectException(Exception::class);
        $this->expectExceptionMessageRegExp('/^Not allowed/');
        $scaffold['resolve'](
            null,
            [
                'ID' => $record->ID
            ],
            [ 'currentUser' => new Member() ],
            new ResolveInfo([])
        );
    }
}
