<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Operations;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Util\ScaffoldingUtil;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\GraphQL\Operations\CopyToStage;
use SilverStripe\Versioned\GraphQL\Types\CopyToStageInputType;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Versioned;
use InvalidArgumentException;

class CopyToStageTest extends SapphireTest
{
    public static $extra_dataobjects = [
        Fake::class,
    ];

    public function testCopyToStage()
    {
        $typeName = ScaffoldingUtil::typeNameForDataObject(Fake::class);
        $manager = new Manager();
        $manager->addType((new CopyToStageInputType())->toType());
        $manager->addType(new ObjectType(['name' => $typeName]));
        $copyToStage = new CopyToStage(Fake::class);
        $scaffold = $copyToStage->scaffold($manager);
        $this->assertInternalType('callable', $scaffold['resolve']);

        /* @var Fake|Versioned $record */
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
                'Input' => [
                    'FromStage' => Versioned::DRAFT,
                    'ToStage' => Versioned::LIVE,
                    'ID' => $record->ID,
                ],
            ],
            [ 'currentUser' => $member ],
            new ResolveInfo([])
        );
        $result = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);
        $this->assertNotNull($result);
        $this->assertEquals($record->ID, $result->ID);

        $oldVersion = $record->Version;
        $record->Name = 'Second';
        $record->write();
        $newVersion = $record->Version;

        $result = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);
        $this->assertEquals($oldVersion, $result->Version);
        $scaffold['resolve'](
            null,
            [
                'Input' => [
                    'FromVersion' => $newVersion,
                    'ToStage' => Versioned::LIVE,
                    'ID' => $record->ID,
                ],
            ],
            [ 'currentUser' => $member ],
            new ResolveInfo([])
        );
        $result = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);
        $this->assertEquals($newVersion, $result->Version);

        $this->expectException(InvalidArgumentException::class);
        $scaffold['resolve'](
            null,
            [
                'Input' => [
                    'ToStage' => Versioned::DRAFT,
                    'ID' => $record->ID,
                ],
            ],
            [ 'currentUser' => new Member() ],
            new ResolveInfo([])
        );
    }
}
