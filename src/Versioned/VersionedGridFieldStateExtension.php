<?php

namespace SilverStripe\Versioned\Versioned;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Versioned\Mode\VersionedGridFieldState\VersionedGridFieldState;

/**
 * Decorates a GridFieldConfig with gridfield publishing state
 *
 * @extends Extension<GridFieldConfig>
 */
class VersionedGridFieldStateExtension extends Extension
{
    protected function updateConfig()
    {
        $owner = $this->getOwner();
        if (!$owner->getComponentByType(VersionedGridFieldState::class)) {
            $owner->addComponent(new VersionedGridFieldState());
        }
    }
}
