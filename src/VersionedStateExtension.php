<?php

namespace SilverStripe\Versioned;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;

/**
 * Persists versioned state between requests via querystring arguments
 */
class VersionedStateExtension extends Extension
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
     * @param string $link
     */
    public function updateLink(&$link)
    {
        // Skip if link already contains reading mode
        if ($this->hasVersionedQuery($link)) {
            return;
        }

        // Skip if current mode matches default mode
        // See LeftAndMain::init() for example of this being overridden.
        if (Versioned::get_reading_mode() === Versioned::get_default_reading_mode()) {
            return;
        }

        // Determine if query args are supported for the current mode
        $queryargs = $this->buildVersionedQuery();
        if (!$queryargs) {
            return;
        }

        // Decorate
        $link = Controller::join_links(
            $link,
            '?' . http_build_query($queryargs)
        );
    }

    /**
     * Check if link contains versioned queryargs
     *
     * @param string $link
     * @return bool
     */
    protected function hasVersionedQuery($link)
    {
        // Find querystrings
        $parts = explode('?', $link, 2);
        if (count($parts) < 2) {
            return false;
        }
        parse_str($parts[1], $localargs);
        // any known keys?
        switch (true) {
            case isset($localargs['stage']):
            case isset($localargs['archiveDate']):
                return true;
            default:
                return false;
        }
    }

    /**
     * Build queryargs array for current mode
     *
     * @return array|null Necessary args, or null if not supported mode
     */
    protected function buildVersionedQuery()
    {
        // Stage args
        $stage = Versioned::get_stage();
        if ($stage) {
            return ['stage' => $stage];
        }

        // Archived args
        $archivedDate = Versioned::current_archived_date();
        if ($archivedDate) {
            return [
                'archiveDate' => $archivedDate,
                'stage' => Versioned::current_archived_stage(),
            ];
        }

        // No args for other modes
        return null;
    }
}
