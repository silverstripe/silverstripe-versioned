<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Operations;

use Exception;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\ORM\SS_List;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\GraphQL\Operations\ReadVersions;
use SilverStripe\Versioned\GraphQL\Types\VersionedStage;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Tests\VersionedTest\UnversionedWithField;

class ReadVersionsTest extends SapphireTest
{

    protected $usesDatabase = true;

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
        $this->assertInternalType('callable', $scaffold['resolve']);

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

        $this->assertInstanceOf(SS_List::class, $result);
        $this->assertCount(3, $result);
        $this->assertInstanceOf(Fake::class, $result->first());
        $this->assertEquals(1, $result->first()->Version);
        $this->assertEquals(3, $result->last()->Version);
    }

    public function testVersionFieldIsSortable()
    {
        $operation = new ReadVersions(Fake::class, 'FakeClass');

        // Omit the test if the API isn't available (must be running silverstripe-graphql < 3)
        if (!method_exists($operation, 'getSortableFields')) {
            $this->markTestSkipped('getSortableFields API is missing');
        }

        $this->assertContains('Version', $operation->getSortableFields());
    }
}
