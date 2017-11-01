<?php

namespace SilverStripe\Versioned;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataExtension;

class MemberExtension extends DataExtension
{
    /**
     * Reset the reading mode when users log out of the CMS
     *
     * @param HTTPRequest|null $request
     */
    public function afterMemberLoggedOut(HTTPRequest $request = null)
    {
        if ($request) {
            $request->getSession()->clear('readingMode');
        }
    }
}
