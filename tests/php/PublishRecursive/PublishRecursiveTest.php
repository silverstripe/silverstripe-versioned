<?php

namespace SilverStripe\Versioned\Tests\PublishRecursive;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Tests\PublishRecursive\TestPage;
use SilverStripe\Versioned\Versioned;

class PublishRecursiveTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        SlowDummyParent::class,
        SlowDummyObject::class,
        TestPage::class,
    ];

    /**
     * @var array
     */
    protected static $required_extensions = [
        SlowDummyParent::class => [
            Versioned::class,
            RecursivePublishable::class,
        ],
        SlowDummyObject::class => [
            Versioned::class,
        ],
    ];

    /**
     * This test validates consistent timestamps of versions created by publish recursive in an object hierarchy
     * We expect that the top level object and all nested objects will end up
     * with the same timestamp of their latest version
     *
     * @throws ValidationException
     */
    public function testPublishRecursiveVersionTiming()
    {
        /** @var SlowDummyObject|Versioned $object */
        $object = SlowDummyObject::create();
        $object->Title = 'Slow Object';
        $object->write();

        /** @var SlowDummyParent|Versioned|RecursivePublishable $parent */
        $parent = SlowDummyParent::create();
        $parent->Title = 'Slow Parent';
        $parent->NestedObjectID = (int) $object->ID;
        $parent->write();
        $parent->publishRecursive();

        $versionsSuffix = '_Versions';
        $parentTable = SlowDummyParent::config()->get('table_name');
        $parentVersionedTable = $parentTable . $versionsSuffix;
        $objectTable = SlowDummyObject::config()->get('table_name');
        $objectVersionedTable = $objectTable . $versionsSuffix;
        $tables = [
            $parentVersionedTable => $parent->ID,
            $objectVersionedTable => $object->ID,
        ];

        $results = [];

        foreach ($tables as $table => $id) {
            $query = SQLSelect::create(
                '"LastEdited"',
                sprintf('"%s"', $table),
                ['"RecordID"' => $id],
                ['"Version"' => 'DESC'],
                [],
                [],
                1
            );

            $results[] = $query->execute()->value();
        }

        $parentEdit = array_shift($results);
        $objectEdit = array_shift($results);

        $this->assertNotEmpty($parentEdit);
        $this->assertNotEmpty($objectEdit);
        $this->assertEquals($parentEdit, $objectEdit);
    }

    /**
     * @dataProvider inferredTitleLocaleProvider
     *
     * @see https://github.com/silverstripe/silverstripe-versioned/issues/539
     */
    public function testPublishRecursiveTruncatesInferredTitle(
        string $locale,
        string $title,
        string $expectedTitleSnippet
    ): void {
        i18n::with_locale($locale, function () use ($title, $expectedTitleSnippet): void {
            $page = TestPage::create();
            $page->Title = $title;
            $page->write();

            DBDatetime::withFixedNow('2020-12-24 12:00:00', function () use ($expectedTitleSnippet, $page): void {
                $created = DBDatetime::now()->Nice();
                $page->publishRecursive();

                /** @var ChangeSet $changeset */
                $changeset = ChangeSet::get()->orderBy('"ChangeSet"."ID" DESC')->first();
                $this->assertNotEmpty($changeset);

                $this->assertStringContainsString($expectedTitleSnippet, $changeset->Name);
                $this->assertStringContainsString('…', $changeset->Name);
                $this->assertStringContainsString($created, $changeset->Name);
                $this->assertLessThanOrEqual(255, mb_strlen($changeset->Name));
            });
        });
    }

    public static function inferredTitleLocaleProvider(): array
    {
        return [
            'English' => [
                'en',
                "To be, or not to be: that is the question: Whether 'tis nobler in the mind to suffer the slings and arrows of outrageous fortune, or to take arms against a sea of troubles and by opposing end them. To die, to sleep—no more.",
                "To be, or not to be: that is the question: Whether 'tis nobler in the mind to suffer the slings and arrows of outrageous fortune",
            ],
            'Czech' => [
                'cs',
                'Být, či nebýt — to je otázka: zda je pro ducha ušlechtilejší trpělivě snášet šípy a střely rozzuřeného osudu, anebo se ozbrojen postavit proti moři lidských strastí a odporem je konečně ukončit. Zemřít, usnout — nic víc.',
                'Být, či nebýt — to je otázka: zda je pro ducha ušlechtilejší trpělivě snášet šípy a střely rozzuřeného osudu',
            ],
        ];
    }
}
