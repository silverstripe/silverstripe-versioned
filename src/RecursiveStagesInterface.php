<?php

namespace SilverStripe\Versioned;

use SilverStripe\ORM\DataObject;

/**
 * Interface RecursiveStagesInterface
 *
 * Interface for @see RecursiveStagesService to provide the capability to for "smart" dirty model state
 * which can cover nested models
 */
interface RecursiveStagesInterface
{
    /**
     * Determine if content differs on stages including nested objects
     */
    public function stagesDifferRecursive(DataObject $object): bool;
}
