<?php

namespace SilverStripe\Versioned\Tests\VersionedTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Core\Extension;

/**
 * Alters stage mode of extended object to be public
 */
class PublicExtension extends Extension implements TestOnly
{
    protected function canViewNonLive($member = null)
    {
        return true;
    }
}
