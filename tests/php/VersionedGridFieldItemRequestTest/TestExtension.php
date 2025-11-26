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
     * Flag to control whether to add actions
     */
    public static bool $add_test_action = false;

    public function updateFormActions(FieldList $actions)
    {
        if (static::$add_test_action) {
            $moreOptions = $actions->findOrMakeTab('ActionMenus.MoreOptions');
            $moreOptions->push(FormAction::create('doTestAction', 'Test Action'));
        }
    }
}
