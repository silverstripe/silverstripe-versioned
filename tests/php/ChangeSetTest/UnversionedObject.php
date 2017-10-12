<?php

namespace SilverStripe\Versioned\Tests\ChangeSetTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class UnversionedObject extends DataObject implements TestOnly
{
    private static $table_name = 'ChangeSetTest_UnversionedObject';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_one = [
        'Parent' => MidObject::class,
    ];
}
