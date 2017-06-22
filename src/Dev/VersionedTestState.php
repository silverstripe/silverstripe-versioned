<?php

namespace SilverStripe\Versioned\Dev;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\State\TestState;
use SilverStripe\Versioned\Versioned;

/**
 * Decorate sapphire test with versioning
 */
class VersionedTestState implements TestState
{
    /**
     * @var string
     */
    protected $readingmode = null;

    public function setUp(SapphireTest $test)
    {
        $this->readingmode = Versioned::get_reading_mode();
    }

    public function tearDown(SapphireTest $test)
    {
        Versioned::set_reading_mode($this->readingmode);
    }

    public function setUpOnce($class)
    {
    }

    public function tearDownOnce($class)
    {
    }
}
