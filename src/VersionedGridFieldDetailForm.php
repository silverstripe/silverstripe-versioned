<?php

namespace SilverStripe\Versioned;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\ORM\DataObject;

/**
 * @extends Extension<GridFieldDetailForm>
 */
class VersionedGridFieldDetailForm extends Extension
{
    /**
     * @param string $class
     * @param GridField $gridField
     * @param DataObject|Versioned $record
     * @param RequestHandler $requestHandler
     * @param string $assignedClass Name of class explicitly assigned to this component
     */
    public function updateItemRequestClass(&$class, $gridField, $record, $requestHandler, $assignedClass = null)
    {
        // Avoid overriding explicitly assigned class name if set using setItemRequestClass()
        if ($assignedClass) {
            return;
        }
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
