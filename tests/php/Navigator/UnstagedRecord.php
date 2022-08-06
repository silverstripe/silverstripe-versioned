<?php

namespace SilverStripe\Versioned\Tests\Navigator;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Admin\Previewable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Versioned but not staged
 */
class UnstagedRecord extends DataObject implements TestOnly, Previewable
{
    private static $table_name = 'SilverStripeNavigatorTest_UnstagedRecord';

    private static $show_stage_link = true;

    private static $show_live_link = true;

    private static $show_unversioned_preview_link = true;

    private static $extensions = [
        Versioned::class . '.versioned',
    ];

    public $previewLinkTestProperty = null;

    public function PreviewLink($action = null)
    {
        return $this->previewLinkTestProperty;
    }

    /**
     * To determine preview mechanism (e.g. embedded / iframe)
     *
     * @return string
     */
    public function getMimeType()
    {
        return 'text/html';
    }

    public function CMSEditLink()
    {
        return null;
    }
}
