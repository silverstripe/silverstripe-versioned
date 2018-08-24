<?php

namespace SilverStripe\Versioned;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;

/**
 * Decorates a GridFieldConfig with a archive action
 */
class VersionedGridFieldArchiveExtension extends Extension
{
    public function updateConfig()
    {
        /** @var GridFieldConfig $owner */
        $owner = $this->getOwner();

        $owner->addComponent(new GridFieldArchiveAction(), GridFieldDeleteAction::class);
    }
}
