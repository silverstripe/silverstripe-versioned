<?php

namespace SilverStripe\Versioned\GraphQL\Operations;

use SilverStripe\GraphQL\Schema\Interfaces\OperationCreator;

if (!class_exists(OperationCreator::class)) {
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
        return 'publish' . ucfirst($typeName);
    }

    /**
     * @return string
     */
    protected function getAction(): string
    {
        return AbstractPublishOperationCreator::ACTION_PUBLISH;
    }

}
