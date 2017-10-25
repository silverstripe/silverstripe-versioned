<?php

namespace SilverStripe\Versioned;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObject;

/**
 * Extends {@see GridFieldDetailForm}
 */
class VersionedGridFieldDetailForm extends Extension
{
    /**
     * @param string $class
     * @param GridField $gridField
     * @param DataObject $record
     * @param RequestHandler $requestHandler
     */
    public function updateItemRequestClass(&$class, $gridField, $record, $requestHandler)
    {
        // Conditionally use a versioned item handler if it doesn't already have one.
        if ($record
            && $record->has_extension(Versioned::class)
            && $record->config()->get('versioned_gridfield_extensions')
            && (!$class || !is_subclass_of($class, VersionedGridFieldItemRequest::class))
        ) {
            $class = VersionedGridFieldItemRequest::class;
        }
    }
}
