<?php

namespace SilverStripe\Versioned\Tests\VersionedGridFieldItemRequestTest;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;

/**
 * Test extension that adds actions via updateFormActions
 */
class TestExtension extends Extension implements TestOnly
{
    /**
     * @var bool Flag to control whether to add actions
     */
    public static $add_test_action = false;

    public function updateFormActions(FieldList $actions)
    {
        if (self::$add_test_action) {
            $moreOptions = $actions->findOrMakeTab('ActionMenus.MoreOptions');
            $moreOptions->push(FormAction::create('doTestAction', 'Test Action'));
        }
    }
}