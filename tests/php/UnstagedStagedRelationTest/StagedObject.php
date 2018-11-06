<?php

namespace SilverStripe\Versioned\Tests\UnstagedStagedRelationTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Versioned\Versioned;

/**
 * Class StagedObject
 * @package SilverStripe\Versioned\Tests\UnstagedStagedRelationTest
 * @method ManyManyList|UnstagedObject[] UnstagedObjects()
 * @mixin Versioned
 */
class StagedObject extends DataObject implements TestOnly
{
    private static $table_name = 'UnstagedStagedRelationTest_StagedObject';

    private static $belongs_many_many = [
        'UnstagedObjects' => UnstagedObject::class,
    ];

    private static $extensions = [
        Versioned::class,
    ];
}
