<?php

namespace SilverStripe\Versioned\Tests\RecursiveStagesServiceTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;

/**
 * Class ColumnObject
 *
 * @property string $Title
 * @property int $PrimaryObject
 * @method PrimaryObject PrimaryObject()
 * @method HasManyList|GroupObject[] Groups()
 * @package SilverStripe\Versioned\Tests\RecursiveStagesServiceTest
 */
class ColumnObject extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'RecursiveStagesServiceTest_ColumnObject';

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
