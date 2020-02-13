<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class VersionedInjectionTest extends SapphireTest
{
    protected static $required_extensions = [
        DataObject::class => [
            Versioned::class,
        ],
    ];

    public function testInjectionWorks(): void
    {
        Injector::inst()->registerService(VersionedInjected::create(), Versioned::class);
        $this->assertEquals('hello', Versioned::singleton()->getIncludingDeleted('', '', ''));
        $this->assertEquals('hello', DataObject::create()->getIncludingDeleted('', '' ,''));
    }
}
