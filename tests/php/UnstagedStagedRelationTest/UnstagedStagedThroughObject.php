<?php

namespace SilverStripe\Versioned\Tests\UnstagedStagedRelationTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Class UnstagedStagedThroughObject
 * @package SilverStripe\Versioned\Tests\UnstagedStagedRelationTest
 * @property int $StagedObjectID
 * @property int $UnstagedObjectID
 * @method StagedObject StagedObject()
 * @method UnstagedObject UnstagedObject()
 */
class UnstagedStagedThroughObject extends DataObject implements TestOnly
{
    private static $table_name = 'UnstagedStagedRelationTest_UnstagedObject_StagedObjects';

    private static $has_one = [
        'StagedObject'   => StagedObject::class,
        'UnstagedObject' => UnstagedObject::class,
    ];

    private static $extensions = [
        Versioned::class . '.versioned',
    ];
}
