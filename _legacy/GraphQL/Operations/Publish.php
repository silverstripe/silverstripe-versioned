<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use SilverStripe\Dev\Deprecation;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\RecursivePublishable;
use SilverStripe\Versioned\Versioned;

// GraphQL dependency is optional in versioned,
// and legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(PublishOperation::class)) {
    return;
}

/**
 * Scaffolds a generic update operation for DataObjects.
 *
 * @deprecated 1.8.0 Use the latest version of graphql instead
 */
class Publish extends PublishOperation
{
    /**
     * @param string $dataObjectClass
     *
     * @return string
     */
    public function __construct($dataObjectClass)
    {
        Deprecation::notice('1.8.0', 'Use the latest version of graphql instead', Deprecation::SCOPE_CLASS);
        parent::__construct($dataObjectClass);
    }

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
    protected function checkPermission(DataObjectInterface $obj, Member $member = null)
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
