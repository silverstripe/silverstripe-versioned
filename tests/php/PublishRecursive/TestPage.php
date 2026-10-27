<?php

namespace SilverStripe\Versioned\Tests\PublishRecursive;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 * @mixin Versioned
 * @mixin RecursivePublishable
 */
class TestPage extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
        RecursivePublishable::class,
    ];

    private static $table_name = 'PublishRecursive_Page';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];
}
