<?php
namespace SilverStripe\Versioned\Tests\ChangeSetTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\Versioned\Staged\ChangeSet;

class ChangeSetSyncStub extends ChangeSet implements TestOnly
{
    public $isSyncCalled = false;

    public function isSynced()
    {
        $this->isSyncCalled = true;
    }
}
