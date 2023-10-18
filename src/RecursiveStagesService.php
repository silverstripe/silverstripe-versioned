<?php

namespace SilverStripe\Versioned;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Resettable;
use SilverStripe\ORM\DataObject;

/**
 * Functionality for detecting the need of publishing nested objects owned by common parent / ancestor object
 */
class RecursiveStagesService implements RecursiveStagesInterface, Resettable
{
    use Injectable;

    private array $stagesDifferCache = [];
    private array $ownedObjectsCache = [];

    public function flushCachedData(): void
    {
        $this->stagesDifferCache = [];
        $this->ownedObjectsCache = [];
    }

    public static function reset(): void
    {
        /** @var RecursiveStagesInterface $service */
        $service = Injector::inst()->get(RecursiveStagesInterface::class);

        if (!$service instanceof RecursiveStagesService) {
            // This covers the case where the service is overridden
            return;
        }

        $service->flushCachedData();
    }

    /**
     * Determine if content differs on stages including nested objects
     * This method also supports non-versioned models to allow traversal of hierarchy
     * which includes both versioned and non-versioned models
     */
    public function stagesDifferRecursive(DataObject $object): bool
    {
        $cacheKey = $object->getUniqueKey();

        if (!array_key_exists($cacheKey, $this->stagesDifferCache)) {
            $this->stagesDifferCache[$cacheKey] = $this->determineStagesDifferRecursive($object);
        }

        return $this->stagesDifferCache[$cacheKey];
    }

    /**
     * Execution ownership hierarchy traversal and inspect individual models
     */
    protected function determineStagesDifferRecursive(DataObject $object): bool
    {
        if (!$object->exists()) {
            // Model hasn't been saved to DB, so we can just bail out as there is nothing to inspect
            return false;
        }

        // Compare existing content (perform full ownership traversal)
        $models = [$object];

        /** @var DataObject|Versioned $model */
        while ($model = array_shift($models)) {
            if ($model->hasExtension(Versioned::class) && $model->isModifiedOnDraft()) {
                // Model is dirty,
                // we can return here as there is no need to check the rest of the hierarchy
                return true;
            }

            // Discover and add owned objects for inspection
            $relatedObjects = $this->getOwnedObjects($model);
            $models = array_merge($models, $relatedObjects);
        }

        // Compare deleted content,
        // this wouldn't be covered in hierarchy traversal as deleted models are no longer present
        $draftIdentifiers = $this->getOwnedIdentifiers($object, Versioned::DRAFT);
        $liveIdentifiers = $this->getOwnedIdentifiers($object, Versioned::LIVE);

        return $draftIdentifiers !== $liveIdentifiers;
    }

    /**
     * Get unique identifiers for all owned objects, so we can easily compare them
     */
    protected function getOwnedIdentifiers(DataObject $object, string $stage): array
    {
        $identifiers = Versioned::withVersionedMode(function () use ($object, $stage): array {
            Versioned::set_stage($stage);

            /** @var DataObject $stagedObject */
            $stagedObject = DataObject::get_by_id($object->ClassName, $object->ID);

            if ($stagedObject === null) {
                return [];
            }

            $models = [$stagedObject];
            $identifiers = [];

            while ($model = array_shift($models)) {
                // Compose a unique identifier, so we can easily compare models across stages
                // Note that we can't use getUniqueKey() as that one contains stage fragments
                // which prevents us from making cross-stage comparison
                $identifiers[] = $model->ClassName . '-' . $model->ID;
                $relatedObjects = $this->getOwnedObjects($model);
                $models = array_merge($models, $relatedObjects);
            }

            return $identifiers;
        });

        sort($identifiers, SORT_STRING);

        return array_values($identifiers);
    }

    /**
     * This lookup will attempt to find "owned" objects
     * This method uses the 'owns' relation, same as @see RecursivePublishable::publishRecursive()
     */
    protected function getOwnedObjects(DataObject $object): array
    {
        if (!$object->hasExtension(RecursivePublishable::class)) {
            return [];
        }

        // Add versioned stage to cache key to cover the case where non-versioned model owns versioned models
        // In this situation the versioned models can have different cached state which we need to cover
        $cacheKey = $object->getUniqueKey() . '-' . Versioned::get_stage();

        if (!array_key_exists($cacheKey, $this->ownedObjectsCache)) {
            $this->ownedObjectsCache[$cacheKey] = $object
                // We intentionally avoid recursive traversal here as it's not memory efficient,
                // stack based approach is used instead for better performance
                ->findOwned(false)
                ->toArray();
        }

        return $this->ownedObjectsCache[$cacheKey];
    }
}
