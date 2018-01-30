<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Operations;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Util\ScaffoldingUtil;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\GraphQL\Operations\Publish;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Versioned;
use Exception;

class PublishTest extends SapphireTest
{
    protected $usesDatabase = true;

    public static $extra_dataobjects = [
        Fake::class,
    ];

    public function testPublish()
    {
        $typeName = ScaffoldingUtil::typeNameForDataObject(Fake::class);
        $manager = new Manager();
        $manager->addType(new ObjectType(['name' => $typeName]));

        $publish = new Publish(Fake::class);
        $scaffold = $publish->scaffold($manager);
        $this->assertInternalType('callable', $scaffold['resolve']);

        $record = new Fake();
        $record->Name = 'First';
        $record->write();

        $result = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);

        $this->assertNull($result);
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

        $this->assertNotNull($result);
        $this->assertInstanceOf(Fake::class, $result);
        $this->assertEquals('First', $result->Name);

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
