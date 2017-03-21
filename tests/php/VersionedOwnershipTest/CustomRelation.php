<?php

namespace SilverStripe\Versioned\Tests\VersionedOwnershipTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Object which is owned via a custom PHP method rather than DB relation
 *
 * @mixin Versioned
 */
class CustomRelation extends DataObject implements TestOnly
{
    private static $extensions = [
        Versioned::class,
    ];

    private static $table_name = 'VersionedOwnershipTest_CustomRelation';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $owned_by = [
        'Pages'
    ];

    /**
     * All pages with the same number. E.g. 'Page 1' owns 'Custom 1'
     *
     * @return DataList
     */
    public function Pages()
    {
        $title = str_replace('Custom', 'Page', $this->Title);
        return TestPage::get()->filter('Title', $title);
    }
}
