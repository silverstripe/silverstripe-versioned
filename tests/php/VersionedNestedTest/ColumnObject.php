<?php

namespace SilverStripe\Versioned\Tests\VersionedNestedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class ColumnObject extends DataObject implements TestOnly
{

    /**
     * @var string
     */
    private static $table_name = 'VersionedNestedTest_ColumnObject';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'PrimaryObject' => PrimaryObject::class,
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'Groups' => GroupObject::class,
    ];

    /**
     * @var array
     */
    private static $owns = [
        'Groups',
    ];

    /**
     * @var array
     */
    private static $cascade_duplicates = [
        'Groups',
    ];

    /**
     * @var array
     */
    private static $cascade_deletes = [
        'Groups',
    ];
}
