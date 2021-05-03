<?php

namespace SilverStripe\Versioned\Tests\VersionedNestedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Class PrimaryObject
 * @package SilverStripe\Versioned\Tests\VersionedNestedTest
 *
 * @mixin Versioned
 */
class PrimaryObject extends DataObject implements TestOnly
{

    /**
     * @var string
     */
    private static $table_name = 'VersionedNestedTest_PrimaryObject';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'Columns' => ColumnObject::class,
    ];

    /**
     * @var array
     */
    private static $owns = [
        'Columns',
    ];

    /**
     * @var array
     */
    private static $cascade_duplicates = [
        'Columns',
    ];

    /**
     * @var array
     */
    private static $cascade_deletes = [
        'Columns',
    ];
}
