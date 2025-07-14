<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class TestGeneratedColumns extends DataObject implements TestOnly
{
    private static string $table_name = 'VersionedTest_TestGeneratedColumns';

    private static array $db = [
        'BaseField' => 'Varchar(255)',
        'GeneratedField1' => 'Generated("Varchar(255)", "CONCAT(\\"BaseField\\", \'_etc\')", "VIRTUAL")',
        'GeneratedField2' => 'Generated("Varchar(255)", "CONCAT(\\"BaseField\\", \'_etc\')", "STORED")',
    ];

    private static array $extensions = [
        Versioned::class,
    ];
}
