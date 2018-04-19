<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

if (!class_exists(PublishOperation::class)) {
    return;
}

/**
 * Scaffolds a generic update operation for DataObjects.
 */
class Publish extends PublishOperation
{
    /**
     * @return string
     */
    protected function createOperationName()
    {
        return 'publish' . ucfirst($this->getTypeName());
    }

    /**
     * @param DataObjectInterface $obj
     */
    protected function doMutation(DataObjectInterface $obj)
    {
        /** @var RecursivePublishable $obj */
        $obj->publishRecursive();
    }

    /**
     * @param DataObjectInterface $obj
     * @param Member $member
     * @return boolean
     */
    protected function checkPermission(DataObjectInterface $obj, Member $member)
    {
        /** @var Versioned $obj */
        return $obj->canPublish($member);
    }

    /**
     * Set the stage for the read query
     */
    protected function getReadingStage()
    {
        return Versioned::DRAFT;
    }
}
