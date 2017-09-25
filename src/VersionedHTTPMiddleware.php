<?php

namespace SilverStripe\Versioned;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Convert;
use SilverStripe\Security\Security;

/**
 * Initialises the versioned stage when a request is made.
 */
class VersionedHTTPMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $next)
    {
        // Ensure Controller::curr() is available
        $dummyController = new Controller();
        $dummyController->setRequest($request);
        $dummyController->pushCurrent();

        // Permission check
        try {
            $result = $this->checkPermissions($request);
            if ($result instanceof HTTPResponse) {
                return $result;
            } else {
                // Set stage
                Versioned::choose_site_stage($request);
            }
            if (Versioned::get_stage() === Versioned::DRAFT || Versioned::current_archived_date()) {
                // work out the current host (and port if non-standard)
                $urlParts = parse_url(Director::absoluteBaseURL());
                $host = $urlParts['host'];
                if (array_key_exists('port', $urlParts) && $urlParts['port'] !== 80 && $urlParts['port'] !== 443) {
                    $host .= ':' . $urlParts['port'];
                }
                $hosts = ini_get('url_rewriter.hosts');
                if ($hosts) {
                    $hosts .= ',';
                }
                $hosts .= $host;
                ini_set('url_rewriter.tags', "a=href,area=href,frame=src,form=,fieldset=");
                ini_set('url_rewriter.hosts', $hosts);
                if (Versioned::get_stage() === Versioned::DRAFT) {
                    output_add_rewrite_var('stage', Versioned::DRAFT);
                } elseif ($date = Versioned::current_archived_date()) {
                    output_add_rewrite_var('archiveDate', $date);
                }
            }
        } finally {
            // Reset dummy controller
            $dummyController->popCurrent();
        }

        // Process
        return $next($request);
    }

    /**
     * @param HTTPRequest $request
     * @return HTTPResponse|true True if ok, httpresponse if error
     */
    protected function checkPermissions(HTTPRequest $request)
    {
        // Block non-authenticated users from setting the stage mode
        if (Versioned::can_choose_site_stage($request)) {
            return true;
        }

        // Build error message
        $link = Convert::raw2xml(Controller::join_links(Director::baseURL(), $request->getURL(), "?stage=Live"));
        $permissionMessage = _t(
            __CLASS__.'.DRAFT_SITE_ACCESS_RESTRICTION',
            'You must log in with your CMS password in order to view the draft or archived content. '
            . '<a href="{link}">Click here to go back to the published site.</a>',
            [ 'link' => $link ]
        );

        // Force output since RequestFilter::preRequest doesn't support response overriding
        return Security::permissionFailure(null, $permissionMessage);
    }
}
