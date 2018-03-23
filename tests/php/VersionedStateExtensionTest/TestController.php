<?php

namespace SilverStripe\Versioned\Tests\VersionedStateExtensionTest;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;

class TestController extends Controller implements TestOnly
{
    private static $url_segment = 'my_controller';
}
