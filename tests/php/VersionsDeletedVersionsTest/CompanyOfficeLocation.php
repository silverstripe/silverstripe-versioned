<?php

namespace SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 *
 * @mixin RecursivePublishable
 * @mixin Versioned
 */
class CompanyOfficeLocation extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionsDeletedVersionsTest_CompanyOfficeLocation';

    private static $has_one = [
        'Company' => CompanyPage::class,
        'Location' => OfficeLocation::class,
    ];

    private static $owns = [
        'Company',
        'Location',
    ];
}
