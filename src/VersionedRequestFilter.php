<?php

namespace SilverStripe\Versioned;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\RequestFilter;
use SilverStripe\Control\Session;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataModel;
use SilverStripe\Security\Security;

/**
 * Initialises the versioned stage when a request is made.
 */
class VersionedRequestFilter implements RequestFilter
{
    public function preRequest(HTTPRequest $request, Session $session, DataModel $model)
    {
        // Bootstrap session so that Session::get() accesses the right instance
        $dummyController = new Controller();
        $dummyController->setSession($session);
        $dummyController->setRequest($request);
        $dummyController->pushCurrent();

        // Block non-authenticated users from setting the stage mode
        if (!Versioned::can_choose_site_stage($request)) {
            $link = Convert::raw2xml(Controller::join_links(Director::baseURL(), $request->getURL(), "?stage=Live"));
            $permissionMessage = _t(
                __CLASS__.'.DRAFT_SITE_ACCESS_RESTRICTION',
                'You must log in with your CMS password in order to view the draft or archived content. '
                . '<a href="{link}">Click here to go back to the published site.</a>',
                [ 'link' => $link ]
            );

            // Force output since RequestFilter::preRequest doesn't support response overriding
            $response = Security::permissionFailure($dummyController, $permissionMessage);
            $session->inst_save();
            $dummyController->popCurrent();
            // Prevent output in testing
            if (class_exists('SilverStripe\\Dev\\SapphireTest', false) && SapphireTest::is_running_test()) {
                throw new HTTPResponse_Exception($response);
            }
            $response->output();
            die;
        }

        Versioned::choose_site_stage();
        $dummyController->popCurrent();
        return true;
    }

    public function postRequest(HTTPRequest $request, HTTPResponse $response, DataModel $model)
    {
        return true;
    }
}
