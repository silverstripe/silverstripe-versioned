<?php

namespace SilverStripe\Versioned\Tests\RecursiveStagesServiceTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;

/**
 * Class PrimaryObject
 *
 * @method HasManyList|ColumnObject[] Columns()
 * @package SilverStripe\Versioned\Tests\RecursiveStagesServiceTest
 */
class PrimaryObject extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'RecursiveStagesServiceTest_PrimaryObject';

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
