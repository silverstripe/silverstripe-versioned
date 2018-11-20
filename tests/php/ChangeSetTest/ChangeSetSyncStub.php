<?php
namespace SilverStripe\Versioned\Tests\ChangeSetTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Versioned\ChangeSet;

class ChangeSetSyncStub extends ChangeSet implements TestOnly
{
    public $isSyncCalled = false;

    public function isSynced()
    {
        $this->isSyncCalled = true;
    }
}
