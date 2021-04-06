<?php

namespace SilverStripe\Versioned;

use SilverStripe\ORM\DataObject;

/**
 * Interface RecursiveStagesInterface
 *
 * Interface for @see RecursiveStagesService to provide the capability to for "smart" durty model state
 * which can cover nested models
 */
interface RecursiveStagesInterface
{

    /**
     * Determine if content differs on stages including nested objects
     *
     * @param DataObject $object
     * @param string $mode
     * @return bool
     */
    public function stagesDifferRecursive(DataObject $object, string $mode): bool;
}
