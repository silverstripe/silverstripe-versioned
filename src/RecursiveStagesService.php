<?php

namespace SilverStripe\Versioned;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\SS_List;

/**
 * Class RecursiveStagesService
 *
 * Functionality for detecting the need of publishing nested objects owned by common parent / ancestor object
 *
 * @package SilverStripe\Versioned
 */
class RecursiveStagesService
{
    use Injectable;

    /**
     * Strong ownership uses 'owns' configuration to determine relationships
     */
    public const OWNERSHIP_STRONG = 'strong';

    /**
     * Weak ownership uses 'cascade_duplicates' configuration to determine relationships
     */
    public const OWNERSHIP_WEAK = 'weak';

    /**
     * Determine if content differs on stages including nested objects
     *
     * @param DataObject $object
     * @param string $mode
     * @return bool
     */
    public function stagesDifferRecursive(DataObject $object, string $mode): bool
    {
        if (!$object->exists()) {
            return false;
        }

        $items = [$object];

        // compare existing content
        while ($item = array_shift($items)) {
            if ($this->checkNeedPublishingItem($item)) {
                return true;
            }

            $relatedObjects = $this->findOwnedObjects($item, $mode);
            $items = array_merge($items, $relatedObjects);
        }

        // compare deleted content
        $draftIdentifiers = $this->findOwnedIdentifiers($object, $mode, Versioned::DRAFT);
        $liveIdentifiers = $this->findOwnedIdentifiers($object, $mode, Versioned::LIVE);

        return $draftIdentifiers !== $liveIdentifiers;
    }

    /**
     * Find all identifiers for owned objects
     *
     * @param DataObject $object
     * @param string $mode
     * @param string $stage
     * @return array
     */
    protected function findOwnedIdentifiers(DataObject $object, string $mode, string $stage): array
    {
        $ids = Versioned::withVersionedMode(function () use ($object, $mode, $stage): array {
            Versioned::set_stage($stage);

            $object = DataObject::get_by_id($object->ClassName, $object->ID);

            if ($object === null) {
                return [];
            }

            $items = [$object];
            $ids = [];

            while ($object = array_shift($items)) {
                $ids[] = implode('_', [$object->baseClass(), $object->ID]);
                $relatedObjects = $this->findOwnedObjects($object, $mode);
                $items = array_merge($items, $relatedObjects);
            }

            return $ids;
        });

        sort($ids, SORT_STRING);

        return array_values($ids);
    }

    /**
     * This lookup will attempt to find "Strongly owned" objects
     * such objects are unable to exist without the current object
     * We will use "cascade_duplicates" setting for this purpose as we can assume that if an object needs to be
     * duplicated along with the owner object, it uses the strong ownership relation
     *
     * "Weakly owned" objects could be looked up via "owns" setting
     * Such objects can exist even without the owner objects as they are often used as shared objects
     * managed independently of their owners
     *
     * @param DataObject $object
     * @param string $mode
     * @return array
     */
    protected function findOwnedObjects(DataObject $object, string $mode): array
    {
        $ownershipType = $mode === self::OWNERSHIP_WEAK
            ? 'owns'
            : 'cascade_duplicates';

        $relations = (array) $object->config()->get($ownershipType);
        $relations = array_unique($relations);
        $result = [];

        foreach ($relations as $relation) {
            $relation = (string) $relation;

            if (!$relation) {
                continue;
            }

            $relationData = $object->{$relation}();

            if ($relationData instanceof DataObject) {
                if (!$relationData->exists()) {
                    continue;
                }

                $result[] = $relationData;

                continue;
            }

            if (!$relationData instanceof SS_List) {
                continue;
            }

            foreach ($relationData as $relatedObject) {
                if (!$relatedObject instanceof DataObject || !$relatedObject->exists()) {
                    continue;
                }

                $result[] = $relatedObject;
            }
        }

        return $result;
    }

    /**
     * @param DataObject|Versioned $item
     * @return bool
     */
    protected function checkNeedPublishingItem(DataObject $item): bool
    {
        if ($item->hasExtension(Versioned::class)) {
            /** @var $item Versioned */
            return !$item->isPublished() || $item->stagesDiffer();
        }

        return false;
    }
}
