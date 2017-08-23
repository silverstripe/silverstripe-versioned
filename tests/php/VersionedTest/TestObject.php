<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Versioned\Versioned;

/**
 * @property string $Name
 * @property string $Title
 * @property string $Content
 * @method TestObject Parent()
 * @method HasManyList Children()
 * @method ManyManyList Related()
 * @mixin Versioned
 */
class TestObject extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedTest_DataObject';

    /**
     * Enable extensions in gridfield
     *
     * @config
     * @var bool
     */
    private static $versioned_gridfield_extensions = true;

    private static $db = [
        "Name" => "Varchar",
        'Title' => 'Varchar',
        'Content' => 'HTMLText',
    ];

    private static $extensions = [
        Versioned::class,
    ];

    private static $has_one = [
        'Parent' => TestObject::class,
    ];

    private static $has_many = [
        'Children' => TestObject::class,
    ];

    private static $many_many = [
        'Related' => RelatedWithoutversion::class,
    ];

    public function canView($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }
        return true;
    }
}
