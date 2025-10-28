<?php

namespace SilverStripe\Versioned;

use LogicException;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridField;

/**
 * Remove versioned components from a non-versioned gridfield
 *
 * @extends Extension<GridField>
 */
class VersionedGridFieldRemoveComponentsExtension extends Extension
{
    protected function onBeforeRenderHolder()
    {
        $owner = $this->getOwner();
        try {
            $modelClass = $owner->getModelClass();
        } catch (LogicException) {
            // noop - it's possible to have a gridfield with custom components that don't rely on columns
            // from the records in the list.
            // Or more likely - an empty gridfield.
            return;
        }

        if (!method_exists($modelClass, 'has_extension') || !$modelClass::has_extension(Versioned::class)) {
            $owner->getConfig()->removeComponentsByType([GridFieldArchiveAction::class, GridFieldRestoreAction::class]);
        }
    }
}
