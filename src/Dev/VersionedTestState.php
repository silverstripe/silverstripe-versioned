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

    /**
     * Default reading mode
     *
     * @var string
     */
    protected $defaultMode = null;

    public function setUp(SapphireTest $test)
    {
        $this->readingmode = Versioned::get_reading_mode();
        $this->defaultMode = Versioned::get_default_reading_mode();
    }

    public function tearDown(SapphireTest $test)
    {
        Versioned::set_reading_mode($this->readingmode);
        Versioned::set_default_reading_mode($this->defaultMode);
    }

    public function setUpOnce($class)
    {
        $this->resetState();
    }

    public function tearDownOnce($class)
    {
        $this->resetState();
    }

    /**
     * Reset to default "null" state both prior to, and following tests
     */
    protected function resetState()
    {
        Versioned::set_reading_mode(null);
        Versioned::set_default_reading_mode(null);
    }
}
