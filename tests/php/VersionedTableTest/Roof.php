<?php

namespace SilverStripe\Versioned\Tests\VersionedTableTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Class Roof
 *
 * @property string $Title
 * @package SilverStripe\Versioned\Tests\VersionedTableTest
 */
class Roof extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'VersionedTableTest_Roof';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(50)',
    ];

    /**
     * @var array
     */
    private static $extensions = [
        Versioned::class,
    ];
}
