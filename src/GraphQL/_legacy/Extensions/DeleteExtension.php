<?php

namespace SilverStripe\Versioned\GraphQL\Extensions;

use Exception;
use SilverStripe\Core\Extension;
use SilverStripe\GraphQL\Scaffolding\Scaffolders\CRUD\Delete;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

/**
 * Extends the @see Delete CRUD scaffolder to unpublish any items first
 */
class DeleteExtension extends Extension
{
    /**
     * Hooks into the `augmentMutation` extension point in @see Delete::resolve
     *
     * @param DataList $objects
     * @param array $args
     * @param array $context
     * @throws Exception
     */
    public function augmentMutation(DataList $objects, $args, $context)
    {
        foreach ($objects as $object) {
            /** @var DataObject&Versioned $object */
            if (!$object->hasExtension(Versioned::class) || !$object->isPublished()) {
                continue;
            }

            if (!$object->canUnpublish($context['currentUser'])) {
                throw new Exception(sprintf(
                    'Cannot unpublish %s with ID %s',
                    get_class($object),
                    $object->ID
                ));
            }

            $object->doUnpublish();
        }
    }
}
