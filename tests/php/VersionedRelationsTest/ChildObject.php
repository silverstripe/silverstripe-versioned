<?php
namespace SilverStripe\Versioned\Tests\VersionedRelationsTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class ChildObject extends DataObject implements TestOnly
{
    private static $table_name = 'VersionRelationsTest_ChildObject';

    private static $db = [
        'Name' => 'Varchar',
    ];

    private static $extensions = [
        Versioned::class,
    ];
}
