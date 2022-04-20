<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use SilverStripe\GraphQL\Schema\Interfaces\OperationCreator;

// GraphQL dependency is optional in versioned,
// and the following implementation relies on existence of this class (in GraphQL v4)
if (!interface_exists(OperationCreator::class)) {
    return;
}

/**
 * Scaffolds a generic update operation for DataObjects.
 */
class PublishCreator extends AbstractPublishOperationCreator
{

    /**
     * @param string $typeName
     * @return string
     */
    protected function createOperationName(string $typeName): string
    {
        return 'publish' . ucfirst($typeName ?? '');
    }

    /**
     * @return string
     */
    protected function getAction(): string
    {
        return AbstractPublishOperationCreator::ACTION_PUBLISH;
    }
}
