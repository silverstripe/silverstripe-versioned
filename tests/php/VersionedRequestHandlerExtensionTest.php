<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Versioned\Versioned;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\VersionedRequestHandlerExtension;

class VersionedRequestHandlerExtensionTest extends SapphireTest
{
    public function tearDown()
    {
        parent::tearDown();

        Versioned::set_stage(Versioned::LIVE);
    }

    public function testUpdateLinkAddsStageParamsInDraftMode()
    {
        Versioned::set_stage(Versioned::DRAFT);
        $ext = new VersionedRequestHandlerExtension();
        $link = 'my-relative-link/?some=var';
        $ext->updateLink($link);
        $this->assertEquals('my-relative-link/?some=var&stage=' . Versioned::DRAFT, $link);
    }

    public function testUpdateLinkAddsStageParamsOnlyOnceInDraftMode()
    {
        Versioned::set_stage(Versioned::DRAFT);
        $ext = new VersionedRequestHandlerExtension();
        $link = 'my-relative-link/?some=var&stage=Stage';
        $ext->updateLink($link);
        $this->assertEquals('my-relative-link/?some=var&stage=' . Versioned::DRAFT, $link);
    }

    public function testUpdateLinkDoesNotAddStageParamsInLiveMode()
    {
        $ext = new VersionedRequestHandlerExtension();
        $link = 'my-relative-link/?some=var';
        $ext->updateLink($link);
        $this->assertEquals('my-relative-link/?some=var', $link);
    }
}