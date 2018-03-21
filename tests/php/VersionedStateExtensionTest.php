<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;

class VersionedStateExtensionTest extends SapphireTest
{
    public function testUpdateLinkAddsStageParamsInDraftMode()
    {
        Versioned::set_stage(Versioned::DRAFT);
        $controller = new VersionedStateExtensionTest\TestController();
        $link = $controller->Link('?some=var');
        $this->assertEquals('my_controller/?some=var&stage=' . Versioned::DRAFT, $link);
    }

    public function testUpdateLinkAddsStageParamsOnlyOnceInDraftMode()
    {
        Versioned::set_stage(Versioned::DRAFT);
        $controller = new VersionedStateExtensionTest\TestController();
        $link = $controller->Link('?some=var&stage=' . Versioned::LIVE);
        $this->assertEquals('my_controller/?some=var&stage=' . Versioned::LIVE, $link);
    }

    public function testUpdateLinkDoesNotAddStageParamsInLiveMode()
    {
        Versioned::set_stage(Versioned::LIVE);
        $controller = new VersionedStateExtensionTest\TestController();
        $link = $controller->Link('?some=var');
        $this->assertEquals('my_controller/?some=var', $link);
    }
}
