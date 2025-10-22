<?php

namespace SilverStripe\Versioned;

use SilverStripe\Core\ClassInfo;
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
        $modelClass = $owner->getModelClass();
        if (!method_exists($modelClass, 'has_extension') || !$modelClass::has_extension(Versioned::class)) {
            $owner->getConfig()->removeComponentsByType([GridFieldArchiveAction::class, GridFieldRestoreAction::class]);
        }
    }
}
