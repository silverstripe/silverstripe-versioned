<?php

namespace SilverStripe\Versioned\Tests\ChangeSetTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Versioned but non-staged object
 */
class UnstagedObject extends DataObject implements TestOnly
{
    private static $table_name = 'ChangeSetTest_UnstagedObject';

    private static $extensions = [
        Versioned::class . '.versioned',
    ];

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_one = [
        'Parent' => MidObject::class,
    ];
}
