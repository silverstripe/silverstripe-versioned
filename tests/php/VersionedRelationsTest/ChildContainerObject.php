<?php
namespace SilverStripe\Versioned\Tests\VersionedRelationsTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Versioned\Versioned;

/**
 * @property HasManyList Children
 * @property string Text
 */
class ChildContainerObject extends DataObject implements TestOnly
{
    private static $table_name = 'VersionRelationsTest_ChildContainerObject';

    private static $db = [
        'Text' => 'Varchar',
    ];

    private static $has_many = [
        'Children' => ChildObject::class,
    ];

    private static $owns = [
        'Children'
    ];

    private static $extensions = [
        Versioned::class,
    ];
}
