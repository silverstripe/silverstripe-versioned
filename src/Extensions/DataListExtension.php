<?php

namespace SilverStripe\Versioned\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataList;
use SilverStripe\Versioned\Versioned;

/**
 * @extends Extension<DataList>
 */
class DataListExtension extends Extension
{
    protected function onPrepopulateCaches(array $ids): void
    {
        $dataClass = $this->getOwner()->dataClass();
        if (!$dataClass::has_extension(Versioned::class)) {
            return;
        }
        // Version number cache
        Versioned::prepopulateVersionNumberCache($dataClass, $ids);
    }
}
