<?php

namespace SilverStripe\Versioned\Tests\VersionsDeletedVersionsTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyThroughList;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

/**
 *
 * @mixin RecursivePublishable
 * @mixin Versioned
 * @method ManyManyThroughList OfficeLocations()
 */
class CompanyPage extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionsDeletedVersionsTest_CompanyPage';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $many_many = [
        'OfficeLocations' => [
            'through' => CompanyOfficeLocation::class,
            'from' => 'Company',
            'to' => 'Location',
        ]
    ];

    private static $owns = [
        'OfficeLocations',
    ];
}
