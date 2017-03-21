<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class RelatedWithoutversion extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedTest_RelatedWithoutVersion';

    private static $db = [
        'Name' => 'Varchar'
    ];

    private static $belongs_many_many = [
        'Related' => TestObject::class
    ];
}
