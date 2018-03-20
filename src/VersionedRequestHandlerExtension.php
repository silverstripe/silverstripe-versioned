<?php

namespace SilverStripe\Versioned;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;

class VersionedRequestHandlerExtension extends Extension
{
    /**
     * Auto-append current stage if we're in draft,
     * to avoid relying on session state for this,
     * and the related potential of showing draft content
     * without varying the URL itself.
     *
     * Assumes that if the user has access to view the current
     * record in draft stage, they can also view other draft records.
     * Does not concern itself with verifying permissions for performance reasons.
     *
     * This should also pull through to form actions.
     *
     * @param String $link
     * @param String $action
     */
    public function updateLink(&$link)
    {
        if(Versioned::get_stage() === Versioned::DRAFT) {
            $link = Controller::join_links($link, '?stage=' . Versioned::DRAFT);
        }
    }
}