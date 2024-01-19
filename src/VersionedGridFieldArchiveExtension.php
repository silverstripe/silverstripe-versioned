<?php

namespace SilverStripe\Versioned;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;

/**
 * Decorates a GridFieldConfig with a archive action
 *
 * @extends Extension<GridFieldConfig>
 */
class VersionedGridFieldArchiveExtension extends Extension
{
    public function updateConfig()
    {
        $owner = $this->getOwner();
        $owner->addComponent(new GridFieldArchiveAction(), GridFieldDeleteAction::class);
    }
}
