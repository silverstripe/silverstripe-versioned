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
     * @param DataObject|Versioned $record
     * @param RequestHandler $requestHandler
     */
    public function updateItemRequestClass(&$class, $gridField, $record, $requestHandler)
    {
        $isVersioned = $record && $record->hasExtension(Versioned::class);
        $isPublishable = $record && $record->hasExtension(RecursivePublishable::class);
        // Conditionally use a versioned item handler if it doesn't already have one.
        if ($record
            && ($isVersioned || $isPublishable)
            && $record->config()->get('versioned_gridfield_extensions')
            && (!$class || !is_subclass_of($class, VersionedGridFieldItemRequest::class))
        ) {
            $class = VersionedGridFieldItemRequest::class;
        }
    }
}
