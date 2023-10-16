<?php

namespace SilverStripe\Versioned;

use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;

class RecursiveStagesExtension extends Extension
{
    /**
     * Make sure to flush cached data in case the data changes
     * Extension point in @see DataObject::onAfterWrite()
     *
     * @return void
     * @throws NotFoundExceptionInterface
     */
    public function onAfterWrite(): void
    {
        RecursiveStagesService::reset();
    }

    /**
     * Make sure to flush cached data in case the data changes
     * Extension point in @see DataObject::onAfterDelete()
     *
     * @return void
     * @throws NotFoundExceptionInterface
     */
    public function onAfterDelete(): void
    {
        RecursiveStagesService::reset();
    }
}
