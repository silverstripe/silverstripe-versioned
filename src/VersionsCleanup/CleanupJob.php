<?php

namespace App\VersionsCleanup;

use Exception;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * @property array $versions
 * @property array $remainingVersions
 */
class CleanupJob extends AbstractQueuedJob
{
    /**
     * @param array $versions
     */
    public function hydrate(array $versions): void
    {
        $this->versions = $versions;
    }

    public function getTitle(): string
    {
        return 'Delete old version records';
    }

    public function getJobType(): int
    {
        return QueuedJob::QUEUED;
    }

    public function setup(): void
    {
        $this->remainingVersions = $this->versions;
        $this->totalSteps = count($this->versions);
    }

    /**
     * @throws Exception
     */
    public function process(): void
    {
        $remaining = $this->remainingVersions;

        if (count($remaining) > 0) {
            $item = array_shift($remaining);
            $id = $item['id'];
            $class = $item['class'];
            $versions = $item['versions'];

            $count = CleanupService::singleton()->deleteVersions($class, $id, $versions);
            $this->addMessage(sprintf('Deleted %d version records', $count));

            // Mark item as processed
            $this->currentStep += 1;
            $this->remainingVersions = $remaining;
        }

        if (count($this->remainingVersions) > 0) {
            return;
        }

        $this->isComplete = true;
    }
}
