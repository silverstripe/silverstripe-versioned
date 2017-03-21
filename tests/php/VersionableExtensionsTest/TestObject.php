<?php

namespace SilverStripe\Versioned\Tests\VersionableExtensionsTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class TestObject extends DataObject implements TestOnly
{
    private static $table_name = 'VersionableExtensionsTest_DataObject';

    private static $db = [
        'Title' => 'Varchar'
    ];

    private static $extensions = [
        Versioned::class,
        TestExtension::class,
    ];

    private static $versionableExtensions = [
        TestExtension::class => ['test1', 'test2', 'test3']
    ];
}
