<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 */
class WithIndexes extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedTest_WithIndexes';

    private static $db = [
        'UniqA' => 'Int',
        'UniqS' => 'Int',
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $indexes = [
        'UniqS_idx' => [
            'type' => 'unique',
            'columns' => ['UniqS'],
        ],
        'UniqA_idx' => [
            'type' => 'unique',
            'name' => 'UniqA_idx',
            'columns' => ['UniqA'],
        ],
    ];
}
