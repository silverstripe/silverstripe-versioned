<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Versioned\VersionedStateExtension;

class VersionedStateExtensionTest extends SapphireTest
{
    protected static $fixture_file = 'VersionedStateExtensionTest.yml';

    protected static $extra_dataobjects = [
        VersionedStateExtensionTest\LinkableObject::class,
    ];

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

    public function testUpdateLinkRespectsQueryArgs()
    {
        Versioned::set_stage(Versioned::DRAFT);

        // New objects in draft should have stage=Stage link
        $obj2 = new VersionedStateExtensionTest\LinkableObject();
        $obj2->URLSegment = 'helloworld';
        $obj2->write();
        $this->assertEquals('item/helloworld/?stage=Stage', $obj2->Link());

        // Objects selected in stage also have stage=Stage link
        $obj1ID = $this->idFromFixture(VersionedStateExtensionTest\LinkableObject::class, 'object1');
        /** @var VersionedStateExtensionTest\LinkableObject $obj1 */
        $obj1 = VersionedStateExtensionTest\LinkableObject::get()->byID($obj1ID);
        $this->assertEquals('item/myobject/?stage=Stage', $obj1->Link());

        // Selecting live-specific version of this object should NOT have stage=Stage querystring
        // This is intentional so we can create cross-stage links
        /** @var VersionedStateExtensionTest\LinkableObject $obj1Live */
        $obj1Live = Versioned::get_by_stage(VersionedStateExtensionTest\LinkableObject::class, Versioned::LIVE)
            ->byID($obj1ID);
        $this->assertEquals('item/myobject/', $obj1Live->Link());
    }

    public function testDontUpdateLeftAndMainLinks()
    {
        $controller = new LeftAndMain();

        $liveClientConfig = $controller->getClientConfig();
        Versioned::set_stage(Versioned::DRAFT);
        $stageClientConfig = $controller->getClientConfig();

        $this->assertEquals(
            $liveClientConfig,
            $stageClientConfig,
            'LeftAndMain Client config should not be affected by versionned stage.'
        );
    }
}
