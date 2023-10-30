<?php

namespace SilverStripe\Versioned\Tests\RecursiveStagesServiceTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;

/**
 * @property string $Title
 * @property int $ColumnID
 * @method ColumnObject Column()
 * @method HasManyList|ChildObject[] Children()
 */
class GroupObject extends DataObject implements TestOnly
{
    private static string $table_name = 'RecursiveStagesServiceTest_GroupObject';

    private static array $db = [
        'Title' => 'Varchar(255)',
    ];

    private static array $has_one = [
        'Column' => ColumnObject::class,
    ];

    private static array $has_many = [
        'Children' => ChildObject::class,
    ];

    private static array $owns = [
        'Children',
    ];

    private static array $cascade_duplicates = [
        'Children',
    ];

    private static array $cascade_deletes = [
        'Children',
    ];
}
