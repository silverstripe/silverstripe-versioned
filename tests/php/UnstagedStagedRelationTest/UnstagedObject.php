<?php

namespace SilverStripe\Versioned\Tests\UnstagedStagedRelationTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Versioned\Versioned;

/**
 * Class UnstagedObject
 * @package SilverStripe\Versioned\Tests\UnstagedStagedRelationTest
 * @method ManyManyList|StagedObject[] StagedObjects()
 * @mixin Versioned
 */
class UnstagedObject extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'UnstagedStagedRelationTest_UnstagedObject';

    private static $many_many = [
        'StagedObjects' => [
            'through' => UnstagedStagedThroughObject::class,
            'from'    => 'UnstagedObject',
            'to'      => 'StagedObject',
        ],
    ];

    private static $extensions = [
        Versioned::class . '.versioned',
    ];
}
