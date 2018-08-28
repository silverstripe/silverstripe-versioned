<?php

namespace SilverStripe\Versioned;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\FieldType\DBField;
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
        $permissionMessage = DBField::create_field('HTMLFragment', _t(
            __CLASS__.'.DRAFT_SITE_ACCESS_RESTRICTION',
            'You must log in with your CMS password in order to view the draft or archived content. '
            . '<a href="{link}">Click here to go back to the published site.</a>',
            ['link' => $link]
        ));

        // Force output since RequestFilter::preRequest doesn't support response overriding
        return Security::permissionFailure(null, DBField::create_field('HTMLVarchar', $permissionMessage));
    }
}
