<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

/**
 * Scaffolds a generic update operation for DataObjects.
 */
class Unpublish extends PublishOperation
{
    /**
     * @return string
     */
    protected function createOperationName()
    {
        return 'unpublish'.ucfirst($this->typeName());
    }

    /**
     * @param DataObjectInterface $obj
     */
    protected function doMutation(DataObjectInterface $obj)
    {
        $obj->doUnpublish();
    }

    /**
     * @param DataObjectInterface $obj
     * @param Member $member
     * @return boolean
     */
    protected function checkPermission(DataObjectInterface $obj, Member $member)
    {
        return $obj->canUnpublish($member);
    }

    /**
     * Set the stage for the read query
     */
    protected function getReadingStage()
    {
        return Versioned::LIVE;
    }
}
