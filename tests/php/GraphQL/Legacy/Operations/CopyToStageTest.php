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
use SilverStripe\Versioned\GraphQL\Operations\CopyToStage;
use SilverStripe\Versioned\GraphQL\Types\CopyToStageInputType;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Versioned;
use InvalidArgumentException;

// GraphQL dependency is optional in versioned,
// and this legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(Manager::class)) {
    return;
}

class CopyToStageTest extends SapphireTest
{

    protected $usesDatabase = true;

    public static $extra_dataobjects = [
        Fake::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(Schema::class)) {
            $this->markTestSkipped('Skipped GraphQL 3 test ' . __CLASS__);
        }
    }


    public function testCopyToStage()
    {
        $typeName = StaticSchema::inst()->typeNameForDataObject(Fake::class);
        $manager = new Manager();
        $manager->addType((new CopyToStageInputType())->toType());
        $manager->addType(new ObjectType(['name' => $typeName]));
        $copyToStage = new CopyToStage(Fake::class);
        $scaffold = $copyToStage->scaffold($manager);
        $this->assertIsCallable($scaffold['resolve']);

        /* @var Fake|Versioned $record */
        $record = new Fake();
        $record->Name = 'First';
        $record->write(); // v1

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
        $recordLive = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);
        $this->assertNotNull($recordLive);
        $this->assertEquals($record->ID, $recordLive->ID);

        $record->Name = 'Second';
        $record->write();
        $newVersion = $record->Version;

        $recordLive = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);
        $this->assertEquals('First', $recordLive->Title);

        // Invoke publish
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
        $recordLive = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);
        $this->assertEquals('Second', $recordLive->Title);

        // Test error
        $this->expectException(\InvalidArgumentException::class);
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
