<?php

namespace SilverStripe\Versioned\Tests\RecursiveStagesServiceTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;

/**
 * @method HasManyList|ColumnObject[] Columns()
 */
class PrimaryObject extends DataObject implements TestOnly
{
    private static string $table_name = 'RecursiveStagesServiceTest_PrimaryObject';

    private static array $db = [
        'Title' => 'Varchar(255)',
    ];

    private static array $has_many = [
        'Columns' => ColumnObject::class,
    ];

    private static array $owns = [
        'Columns',
    ];

    private static array $cascade_duplicates = [
        'Columns',
    ];

    private static array $cascade_deletes = [
        'Columns',
    ];
}
