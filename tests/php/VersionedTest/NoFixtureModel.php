<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * DO NOT make fixtures with this model.
 * We need to create all its records from scratch to ensure we don't get fixture results when we don't expect them.
 * This is important because we're fetching things like archived and deleted records, so we can't just create and then
 * delete the fixtures.
 */
class NoFixtureModel extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedTest_NoFixtureModel';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $extensions = [
        Versioned::class,
    ];
}
