<?php

namespace SilverStripe\Versioned;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Versioned\VersionedGridFieldState\VersionedGridFieldState;

/**
 * Decorates a GridFieldConfig with gridfield publishing state
 *
 * @extends Extension<GridFieldConfig>
 * @deprecated 2.4.0 Will be removed without equivalent functionality to replace it in a future major release
 */
class VersionedGridFieldStateExtension extends Extension
{
    public function __construct()
    {
        Deprecation::noticeWithNoReplacment('2.4.0', scope: Deprecation::SCOPE_CLASS);
    }

    public function updateConfig()
    {
        $owner = $this->getOwner();
        if (!$owner->getComponentByType(VersionedGridFieldState::class)) {
            $owner->addComponent(new VersionedGridFieldState());
        }
    }
}
