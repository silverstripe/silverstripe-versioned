<?php

namespace App\Tasks\Tools;

use App\Helpers\QueueLockHelper;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;

class ChangeSetCleanupTask extends BuildTask
{
    /**
     * ChangeSet lifetime in days
     * Any records older than this duration are considered obsolete and ready for deletion
     */
    private const DELETION_LIFETIME = 100;

    /**
     * Number of ChangeSet records to be deleted in one go
     * Note that this does directly impact number of related records so the actual number of deleted records may vary
     */
    private const DELETION_LIMIT = 500;

    /**
     * @var string
     */
    private static $segment = 'change-set-cleanup-task';

    /**
     * @var string
     */
    protected $title = 'Remove old ChangeSet data';

    /**
     * @var string
     */
    protected $description = 'Regular cleanup of ChangeSet related data (Campaign admin module related)';

    /**
     * @param HTTPRequest $request
     */
    public function run($request): void // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        if (QueueLockHelper::isMaintenanceLockActive()) {
            return;
        }

        $mainTableRaw = ChangeSet::config()->get('table_name');
        $itemTableRaw = ChangeSetItem::config()->get('table_name');
        $relationTableRaw = $itemTableRaw . '_ReferencedBy';
        $mainTable = sprintf('"%s"', $mainTableRaw);
        $itemTable = sprintf('"%s"', $itemTableRaw);
        $relationTable = sprintf('"%s"', $relationTableRaw);
        $deletionDate = DBDatetime::now()
            ->modify(sprintf('- %d days', self::DELETION_LIFETIME))
            ->Rfc2822();

        $ids = ChangeSet::get()
            ->filter(['LastEdited:LessThan' => $deletionDate])
            ->sort('ID', 'ASC')
            ->limit(self::DELETION_LIMIT)
            ->columnUnique('ID');

        if (count($ids) === 0) {
            echo 'Nothing to delete.' . PHP_EOL;

            return;
        }

        $query = SQLDelete::create(
            [
                $mainTable,
            ],
            [
                sprintf($mainTable . '."ID" IN (%s)', DB::placeholders($ids)) => $ids,
            ],
            [
                $mainTable,
                $itemTable,
                $relationTable,
            ],
        );

        $query
            ->addLeftJoin($itemTableRaw, sprintf('%s."ID" = %s."ChangeSetID"', $mainTable, $itemTable))
            ->addLeftJoin($relationTableRaw, sprintf('%s."ID" = %s."ChangeSetItemID"', $itemTable, $relationTable));

        $result = $query->execute();

        if ($result === null) {
            echo 'Failed to execute deletion.' . PHP_EOL;

            return;
        }

        echo sprintf('Deleted %d records.', DB::affected_rows()) . PHP_EOL;
    }
}
