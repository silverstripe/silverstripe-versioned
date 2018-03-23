<?php

namespace SilverStripe\Versioned\Tests\VersionedStateExtensionTest;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Unversioned object. Links to this object should still contain stage params
 *
 * @property string $URLSegment
 */
class LinkableObject extends DataObject implements TestOnly
{
    private static $table_name = 'VersionedStageExtensionTest_LinkableObject';

    private static $db = [
        'Title' => 'Varchar',
        'URLSegment' => 'Varchar',
    ];

    /**
     * Get link for this object
     *
     * @param string $action
     * @return string
     */
    public function Link($action = null)
    {
        $link =  Controller::join_links('item', $this->URLSegment, $action, '/');
        $this->extend('updateLink', $link, $action);
        return $link;
    }
}
