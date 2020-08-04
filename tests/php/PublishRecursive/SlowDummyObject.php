<?php

namespace SilverStripe\Versioned\Tests\PublishRecursive;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class SlowDummyObject
 *
 * @method SlowDummyPage Parent()
 * @package SilverStripe\Versioned\Tests\PublishRecursive
 */
class SlowDummyObject extends DataObject implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'SlowDummyObject';

    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $belongs_to = [
        'Parent' => SlowDummyPage::class . '.NestedObject',
    ];

    /**
     * @var array
     */
    private static $owned_by = [
        'Parent',
    ];

    protected function onBeforeWrite()
    {
        sleep(2);
        parent::onBeforeWrite();
    }
}
