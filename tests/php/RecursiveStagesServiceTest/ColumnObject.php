<?php

namespace SilverStripe\Versioned\Tests\RecursiveStagesServiceTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;

/**
 * @property string $Title
 * @property int $PrimaryObject
 * @method PrimaryObject PrimaryObject()
 * @method HasManyList|GroupObject[] Groups()
 */
class ColumnObject extends DataObject implements TestOnly
{
    private static string $table_name = 'RecursiveStagesServiceTest_ColumnObject';

    private static array $db = [
        'Title' => 'Varchar(255)',
    ];

    private static array $has_one = [
        'PrimaryObject' => PrimaryObject::class,
    ];

    private static array $has_many = [
        'Groups' => GroupObject::class,
    ];

    private static array $owns = [
        'Groups',
    ];

    private static array $cascade_duplicates = [
        'Groups',
    ];

    private static array $cascade_deletes = [
        'Groups',
    ];
}
