<?php

namespace App\VersionsCleanup;

use App\Extensions\SiteConfig\SiteConfigFeatureFlagsExtension;
use App\Helpers\QueueLockHelper;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ValidationException;
use SilverStripe\SiteConfig\SiteConfig;

class CleanupTask extends BuildTask
{
    /**
     * @var string
     */
    private static $segment = 'versions-cleanup-task';

    /**
     * @var string
     */
    protected $title = 'Delete old version records';

    /**
     * @var string
     */
    protected $description = 'Create delete jobs for old version records';

    /**
     * @param HTTPRequest $request
     * @throws ValidationException
     */
    public function run($request) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        if (QueueLockHelper::isMaintenanceLockActive()) {
            return;
        }

        /** @var SiteConfig|SiteConfigFeatureFlagsExtension $config */
        $config = SiteConfig::current_site_config();

        if (!$config->VersionsCleanup) {
            return;
        }

        CleanupService::singleton()->processVersionsForDeletion();
    }
}
