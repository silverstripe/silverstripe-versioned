<?php


namespace SilverStripe\Versioned\Dev;

use SilverStripe\Dev\TestSession;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Versioned\VersionedStateExtension;

/**
 * Decorates TestSession object to update get / post requests with versioned querystring arguments.
 * Session vars assigned by FunctionalTest::useDraftSite are respected here.
 *
 * @deprecated 2.2..3.0 Use ?stage= querystring arguments instead of session
 * @property TestSession $owner
 */
class VersionedTestSessionExtension extends VersionedStateExtension
{
    /**
     * Update link
     *
     * @param string $url
     */
    public function updateLink(&$url)
    {
        $session = $this->owner->session();
        if (!$session) {
            return;
        }

        // Set unsecured draft
        $unsecuredDraft = $session->get('unsecuredDraftSite');
        if (isset($unsecuredDraft)) {
            Versioned::set_draft_site_secured(!$unsecuredDraft);
        }

        // Set reading mode
        $readingMode = $session->get('readingMode');
        if ($readingMode) {
            parent::updateLink($url);
        }
    }

    /**
     * Get reading mode set by FunctionalTest::useDraftSite()
     *
     * @return string
     */
    protected function getReadingmode()
    {
        // Set reading mode
        return $this->owner->session()->get('readingMode');
    }


    /**
     * Decorate link prior to http get request
     *
     * @param string $link
     */
    public function updateGetURL(&$link)
    {
        $this->updateLink($link);
    }

    /**
     * Decorate link prior to http post request
     *
     * @param string $link
     */
    public function updatePostURL(&$link)
    {
        // Default to same as http get
        $this->updateLink($link);
    }
}
