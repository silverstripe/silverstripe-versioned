<?php

namespace SilverStripe\Versioned\Tests\PublishRecursive;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class SlowDummyParent
 *
 * @property string $Title
 * @property int $NestedObjectID
 * @method SlowDummyObject NestedObject()
 * @package SilverStripe\Versioned\Tests\PublishRecursive
 */
class SlowDummyParent extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'SlowDummyParent';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'NestedObject' => SlowDummyObject::class,
    ];

    /**
     * @var array
     */
    private static $owns = [
        'NestedObject',
    ];

    /**
     * @var array
     */
    private static $cascade_deletes = [
        'NestedObject',
    ];

    /**
     * @var array
     */
    private static $cascade_duplicates = [
        'NestedObject',
    ];

    protected function onBeforeWrite()
    {
        sleep(2);
        parent::onBeforeWrite();
    }
}
