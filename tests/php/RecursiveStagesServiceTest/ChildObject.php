<?php

namespace SilverStripe\Versioned\Tests\RecursiveStagesServiceTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * @property string $Title
 * @property int $GroupID
 * @method GroupObject Group()
 */
class ChildObject extends DataObject implements TestOnly
{
    private static string $table_name = 'RecursiveStagesServiceTest_ChildObject';

    private static array $db = [
        'Title' => 'Varchar(255)',
    ];

    private static array $has_one = [
        'Group' => GroupObject::class,
    ];
}
