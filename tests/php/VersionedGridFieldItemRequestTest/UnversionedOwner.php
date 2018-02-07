<?php

namespace SilverStripe\Versioned\Tests\VersionedGridFieldItemRequestTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class UnversionedObject extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedGridFieldItemRequestTest_UnversionedObject';

    private static $db = [
        'Title' => 'Varchar',
    ];
}
