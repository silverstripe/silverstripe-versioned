<?php


namespace SilverStripe\Versioned\Dev;

use SilverStripe\Dev\TestSession;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Versioned\VersionedStateExtension;

/**
 * Decorates TestSession object to update get / post requests with versioned querystring arguments
 *
 * @property TestSession $owner
 */
class VersionedTestSessionExtension extends VersionedStateExtension
{
    /**
     * Decorate link prior to http get request
     *
     * @param string $url
     */
    public function updateGetURL(&$url)
    {
        $session = $this->owner->session();
        if (!$session) {
            return;
        }
        $readingMode = $session->get('readingMode');
        if (!$readingMode) {
            return;
        }

        // Set to reading mode, decorate link
        Versioned::withVersionedMode(function () use ($readingMode, &$url) {
            Versioned::set_reading_mode($readingMode);
            $this->updateLink($url);
        });
    }

    /**
     * Decorate link prior to http post request
     *
     * @param string $url
     */
    public function updatePostURL(&$url)
    {
        // Default to same as http get
        $this->updateGetURL($url);
    }


}
