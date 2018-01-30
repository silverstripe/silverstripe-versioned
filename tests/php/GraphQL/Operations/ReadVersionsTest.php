<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Operations;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\GraphQL\Operations\ReadVersions;
use SilverStripe\Versioned\GraphQL\Types\VersionedStage;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Tests\VersionedTest\UnversionedWithField;
use Exception;
use SilverStripe\Versioned\Versioned_Version;

class ReadVersionsTest extends SapphireTest
{
    public static $extra_dataobjects = [
        Fake::class,
    ];

    public function testItThrowsIfAppliedToAnUnversionedObject()
    {
        $manager = new Manager();
        $manager->addType((new VersionedStage())->toType());
        $manager->addType(new ObjectType(['name' => 'Test']));
        $readVersions = new ReadVersions(UnversionedWithField::class, 'Test');
        $readVersions->setUsePagination(false);
        $scaffold = $readVersions->scaffold($manager);
        $this->assertInternalType('callable', $scaffold['resolve']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageRegExp('/must have the Versioned extension/');
        $scaffold['resolve'](
            new UnversionedWithField(),
            [],
            ['currentUser' => new Member()],
            new ResolveInfo([])
        );
    }

    public function testItThrowsIfYouCantReadStages()
    {
        $manager = new Manager();
        $manager->addType((new VersionedStage())->toType());
        $manager->addType(new ObjectType(['name' => 'Test']));
        $readVersions = new ReadVersions(Fake::class, 'Test');
        $readVersions->setUsePagination(false);
        $scaffold = $readVersions->scaffold($manager);
        $this->assertTrue(is_callable($scaffold['resolve']));

        $this->expectException(Exception::class);
        $this->expectExceptionMessageRegExp('/Cannot view versions/');
        $scaffold['resolve'](
            new Fake(),
            [],
            ['currentUser' => new Member()],
            new ResolveInfo([])
        );
    }

    public function testItReadsVersions()
    {
        $manager = new Manager();
        $manager->addType((new VersionedStage())->toType());
        $manager->addType(new ObjectType(['name' => 'Test']));
        $readVersions = new ReadVersions(Fake::class, 'Test');
        $readVersions->setUsePagination(false);
        $scaffold = $readVersions->scaffold($manager);
        $this->assertInternalType('callable', $scaffold['resolve']);
        $this->logInWithPermission('ADMIN');
        $member = Security::getCurrentUser();

        $record = new Fake();
        $record->Name = 'First';
        $record->write();

        $record->Name = 'Second';
        $record->write();

        $record->Name = 'Third';
        $record->write();

        $result = $scaffold['resolve'](
            $record,
            [],
            ['currentUser' => $member],
            new ResolveInfo([])
        );

        $this->assertInstanceOf(ArrayList::class, $result);
        $this->assertCount(3, $result);
        $this->assertInstanceOf(Versioned_Version::class, $result->first());
        $this->assertEquals(3, $result->first()->Version);
        $this->assertEquals(1, $result->last()->Version);
    }
}
