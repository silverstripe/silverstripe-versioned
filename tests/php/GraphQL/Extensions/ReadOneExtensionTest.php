<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Extensions;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use InvalidArgumentException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\ReadOne;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\GraphQL\Types\VersionedInputType;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Versioned;

class ReadOneExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    public static $extra_dataobjects = [
        Fake::class,
    ];

    public function testReadOneExtensionAppliesFilters()
    {
        $manager = new Manager();
        $manager->addType((new VersionedInputType())->toType());
        $manager->addType(new ObjectType(['name' => StaticSchema::inst()->typeNameForDataObject(Fake::class)]));
        $read = new ReadOne(Fake::class);
        $readScaffold = $read->scaffold($manager);
        $this->assertInternalType('callable', $readScaffold['resolve']);
        $doResolve = function ($mode, $ID, $version = null) use ($readScaffold) {
            $args = [
                'ID' => $ID,
                'Versioning' => [
                    'Mode' => $mode,
                ],
            ];
            if ($version) {
                $args['Versioning']['Version'] = $version;
            }

            return $readScaffold['resolve'](
                null,
                $args,
                ['currentUser' => Security::getCurrentUser()],
                new ResolveInfo([])
            );
        };

        /* @var Fake|Versioned $record */
        $record = new Fake();
        $record->Name = 'First';
        $record->write();

        $record->Name = 'Second';
        $record->write();

        $result = $doResolve(Versioned::LIVE, $record->ID);
        $this->assertNull($result);

        $result = $doResolve(Versioned::DRAFT, $record->ID);
        $this->assertInstanceOf(Fake::class, $result);
        $this->assertEquals($record->ID, $result->ID);
        $this->assertEquals(2, $record->Version);

        $record->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
        $result = $doResolve(Versioned::LIVE, $record->ID);
        $this->assertInstanceOf(Fake::class, $result);
        $this->assertEquals($record->ID, $result->ID);
        $this->assertEquals(2, $record->Version);

        $record->Name = 'Third';
        $record->write();

        $result = $doResolve('version', $record->ID, 1);
        $this->assertInstanceOf(Fake::class, $result);
        $this->assertEquals($record->ID, $result->ID);
        $this->assertEquals('First', $result->Name);

        $this->expectException(InvalidArgumentException::class);
        $doResolve('version', $record->ID, null);
    }
}
