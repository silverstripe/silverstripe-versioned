<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Versioned\GraphQL\Types\CopyToStageInputType;
use SilverStripe\Versioned\GraphQL\Types\VersionedInputType;
use SilverStripe\Versioned\GraphQL\Types\VersionedQueryMode;
use SilverStripe\Versioned\GraphQL\Types\VersionedStage;
use SilverStripe\Versioned\GraphQL\Types\VersionedStatus;
use SilverStripe\Versioned\GraphQL\Types\VersionSortType;

// GraphQL dependency is optional in versioned,
// and legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(Manager::class)) {
    return;
}

/**
 * @deprecated 4.8..5.0 Use silverstripe/graphql:^4 functionality.
 */
class ManagerExtension extends Extension
{
    /**
     * Adds the versioned types to all schemas
     *
     * @param $config
     */
    public function updateConfig(&$config)
    {
        if (!isset($config['types'])) {
            $config['types'] = [];
        }

        $config['types']['VersionedStage'] = VersionedStage::class;
        $config['types']['VersionedStatus'] = VersionedStatus::class;
        $config['types']['VersionedQueryMode'] = VersionedQueryMode::class;
        $config['types']['VersionedInputType'] = VersionedInputType::class;
        $config['types']['CopyToStageInputType'] = CopyToStageInputType::class;
        $config['types']['VersionSortType'] = VersionSortType::class;
    }
}
