<?php

namespace SilverStripe\Versioned;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\SS_List;
use SilverStripe\Versioned\Versioned;

/**
 * Class PublishStateHelper
 *
 * functionality which is related to detecting the need of publishing nested objects within a block page
 *
 * @package App\Helpers
 */
class PublishStateHelper
{
    /**
     * @param DataObject|Versioned|null $item
     * @return bool
     */
    public static function checkNeedPublishingItem(?DataObject $item): bool
    {
        if ($item === null || !$item->exists()) {
            return false;
        }

        if ($item->hasExtension(Versioned::class)) {
            /** @var $item Versioned */
            return !$item->isPublished() || $item->stagesDiffer();
        }

        return false;
    }

    /**
     * @param SS_List $list
     * @return bool
     */
    public static function checkNeedPublishingList(SS_List $list): bool
    {
        /** @var $item Versioned */
        foreach ($list as $item) {
            if (static::checkNeedPublishingItem($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param DataList $items
     * @param int $parentId
     * @return bool
     */
    public static function checkNeedPublishVersionedItems(DataList $items, int $parentId): bool
    {
        // check for differences in models
        foreach ($items as $item) {
            if (PublishStateHelper::checkNeedPublishingItem($item)) {
                return true;
            }
        }

        // check for deletion of a model
        $draftCount = $items->count();

        // we need to fetch live records and compare amount because if a record was deleted from stage
        // the above draft items loop will not cover the missing item
        $liveCount = Versioned::get_by_stage(
            $items->dataClass(),
            Versioned::LIVE,
            ['ParentID' => $parentId]
        )->count();

        if ($draftCount != $liveCount) {
            return true;
        }

        return false;
    }
}
