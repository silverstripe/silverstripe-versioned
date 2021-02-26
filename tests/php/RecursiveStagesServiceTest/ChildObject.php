<?php

namespace SilverStripe\Versioned\Tests\RecursiveStagesServiceTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class ChildObject
 *
 * @property string $Title
 * @property int $GroupID
 * @method GroupObject Group()
 * @package SilverStripe\Versioned\Tests\RecursiveStagesServiceTest
 */
class ChildObject extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'RecursiveStagesServiceTest_ChildObject';

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
        'Group' => GroupObject::class,
    ];
}
