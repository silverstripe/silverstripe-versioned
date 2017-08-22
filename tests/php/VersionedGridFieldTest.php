<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\CSSContentParser;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Versioned\Versioned;

class VersionedGridFieldTest extends FunctionalTest
{
    protected static $fixture_file = 'VersionedTest.yml';

    protected function setUp()
    {
        parent::setUp();
        $this->useDraftSite(true);
    }

    protected static $extra_controllers = [
        VersionedGridFieldTest\TestController::class,
    ];

    public static function getExtraDataObjects()
    {
        return VersionedTest::getExtraDataObjects();
    }

    public function testEditForm()
    {
        $this->logInWithPermission('ADMIN');
        $response = $this->get('VersionedGridFieldTest_Controller');
        $this->assertFalse($response->isError());

        $parser = new CSSContentParser($response->getBody());
        $editlinkitem = $parser->getBySelector('.ss-gridfield-items .first .edit-link');
        $editlink = (string)$editlinkitem[0]['href'];

        $response = $this->get($editlink);
        $this->assertFalse($response->isError());

        $parser = new CSSContentParser($response->getBody());
        $editform = $parser->getBySelector('#Form_ItemEditForm');
        $editformurl = (string)$editform[0]['action'];

        // Modify this
        $response = $this->post(
            $editformurl,
            [
                'Title' => 'Page 1 renamed',
                'action_doPublish' => 1
            ]
        );
        $this->assertFalse($response->isError());

        // Ensure page is on live and draft
        $recordID = $this->idFromFixture(VersionedTest\TestObject::class, 'page1');
        $draftPage = Versioned::get_by_stage(VersionedTest\TestObject::class, Versioned::DRAFT)->byID($recordID);
        $livePage = Versioned::get_by_stage(VersionedTest\TestObject::class, Versioned::LIVE)->byID($recordID);

        $this->assertNotNull($draftPage);
        $this->assertNotNull($livePage);
        $this->assertEquals('Page 1 renamed', $draftPage->Title);
        $this->assertEquals('Page 1 renamed', $livePage->Title);
    }
}
