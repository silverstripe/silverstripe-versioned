<?php

namespace SilverStripe\Versioned;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;

/**
 * Provides recursive publishable behaviour for LeftAndMain and GridFieldDetailForm_ItemRequest
 */
class RecursivePublishableHandler extends Extension
{
    /**
     * Ensure that non-versioned records are published on save.
     * @param DataObject $record
     */
    public function onAfterSave(DataObject $record)
    {
        // Assume that any versioned record has an explicit publish already
        if (!$record->hasExtension(Versioned::class)) {
            /** @var RecursivePublishable|DataObject $record */
            $record->publishRecursive();
        }
    }
}
