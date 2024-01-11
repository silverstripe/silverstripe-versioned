<?php

namespace SilverStripe\Versioned;

use SilverStripe\Core\Extensible;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataQuery;

/**
 * Applies correct stage to tables
 *
 * @extends Extension<DataQuery>
 */
class VersionedTableDataQueryExtension extends Extension
{
    /**
     * Extension point in @see DataQuery::getJoinTableName()
     *
     * @param string $class
     * @param string $table
     * @param string $updated
     */
    public function updateJoinTableName($class, $table, &$updated)
    {
        $inst = Injector::inst()->get($class);
        if (!$inst->hasExtension(Versioned::class) || !$inst->hasStages()) {
            return;
        }

        $versioned = Injector::inst()->get(Versioned::class);
        $stage = $versioned->get_stage();
        $updated = $versioned->stageTable($table, $stage);
    }
}
