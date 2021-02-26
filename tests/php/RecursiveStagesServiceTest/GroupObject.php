<?php

namespace SilverStripe\Versioned\Tests\RecursiveStagesServiceTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;

/**
 * Class GroupObject
 *
 * @property string $Title
 * @property int $ColumnID
 * @method ColumnObject Column()
 * @method HasManyList|ChildObject[] Children()
 * @package SilverStripe\Versioned\Tests\RecursiveStagesServiceTest
 */
class GroupObject extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'RecursiveStagesServiceTest_GroupObject';

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
        'Column' => ColumnObject::class,
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'Children' => ChildObject::class,
    ];

    /**
     * @var array
     */
    private static $owns = [
        'Children',
    ];

    /**
     * @var array
     */
    private static $cascade_duplicates = [
        'Children',
    ];

    /**
     * @var array
     */
    private static $cascade_deletes = [
        'Children',
    ];
}
