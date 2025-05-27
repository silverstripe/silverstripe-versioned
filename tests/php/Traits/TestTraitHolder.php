<?php

namespace SilverStripe\Versioned\Tests\Traits;

use SilverStripe\Versioned\Traits\VersionNumberCacheTrait;
use SilverStripe\Versioned\Traits\VersionsCacheTrait;
use SilverStripe\Dev\TestOnly;

class TestTraitHolder implements TestOnly
{
    use VersionNumberCacheTrait;
}
