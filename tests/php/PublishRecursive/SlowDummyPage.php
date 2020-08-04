<?php

namespace SilverStripe\Versioned\Tests\PublishRecursive;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * Class SlowDummyPage
 *
 * @property int $NestedObjectID
 * @method SlowDummyObject NestedObject()
 * @package SilverStripe\Versioned\Tests\PublishRecursive
 */
class SlowDummyPage extends SiteTree implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'SlowDummyPage';

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
