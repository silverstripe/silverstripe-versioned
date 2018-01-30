<?php

namespace SilverStripe\Versioned\Tests\GraphQL\Extensions;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\GraphQL\Manager;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\ReadOne;
use SilverStripe\GraphQL\Scaffolding\Util\ScaffoldingUtil;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\GraphQL\Types\VersionedReadOneInputType;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Versioned;
use InvalidArgumentException;

class ReadOneExtensionTest extends SapphireTest
{
    public static $extra_dataobjects = [
        Fake::class,
    ];

    public function testReadOneExtensionAppliesFilters()
    {
        $manager = new Manager();
        $manager->addType((new VersionedReadOneInputType())->toType());
        $manager->addType(new ObjectType(['name' => ScaffoldingUtil::typeNameForDataObject(Fake::class)]));
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
                ['currentUser' => new Member()],
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
