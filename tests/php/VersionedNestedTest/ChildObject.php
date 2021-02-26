<?php

namespace SilverStripe\Versioned\Tests\VersionedNestedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class ChildObject extends DataObject implements TestOnly
{

    /**
     * @var string
     */
    private static $table_name = 'VersionedNestedTest_ChildObject';

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
