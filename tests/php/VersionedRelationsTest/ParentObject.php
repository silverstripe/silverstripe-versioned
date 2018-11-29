<?php
namespace SilverStripe\Versioned\Tests\VersionedRelationsTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * @property ChildContainerObject ChildContainer
 */
class ParentObject extends DataObject implements TestOnly
{
    private static $table_name = 'VersionRelationsTest_ParentObject';

    private static $db = [
        'Name' => 'Varchar',
    ];

    private static $has_one = [
        'ChildContainer' => ChildContainerObject::class,
    ];

    private static $owns = [
        'ChildContainer'
    ];

    private static $extensions = [
        Versioned::class,
    ];
}
