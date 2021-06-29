<?php

namespace App\VersionsCleanup;

use App\Models\Item\BaseItem;
use App\Models\Relation\BaseRelation;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;

class CleanupService
{

    use Injectable;
    use Configurable;

    /**
     * Number of latest versions which will always be kept
     *
     * @var int
     */
    private static $keep_limit = 2;

    /**
     * Delete only versions older than this limit in days
     *
     * @var int
     */
    private static $keep_lifetime = 180;

    /**
     * Number of records processed in one deletion run per base class
     *
     * @var int
     */
    private static $deletion_record_limit = 100;

    /**
     * Number of versions deleted in one deletion run per record
     *
     * @var int
     */
    private static $deletion_version_limit = 100;

    /**
     * Size of the deletion batch which drives how many jobs are created
     * Setting this to 0 will put all deletion data into a single job
     *
     * @var int
     */
    private static $deletion_batch_size = 10;

    /**
     * @var array
     */
    private static $base_classes = [
        SiteTree::class,
        File::class,
        ElementalArea::class,
        BaseElement::class,
        BaseItem::class,
        BaseRelation::class,
    ];

    /**
     * Find versions that need to be deleted and package them into jobs
     *
     * @throws ValidationException
     */
    public function processVersionsForDeletion(): void
    {
        $classes = $this->getBaseClasses();

        foreach ($classes as $class) {
            // Process only one class at a time so we don't exhaust memory
            $records = $this->getRecordsForDeletion([$class]);
            $versions = $this->getVersionsForDeletion($records);
            $this->queueDeletionJobsForVersionGroups($versions);
        }
    }

    /**
     * Get list of valid base classes that we need to process
     *
     * @return array
     */
    public function getBaseClasses(): array
    {
        $classes = $this->config()->get('base_classes');

        return array_filter($classes, static function ($class) {
            $singleton = DataObject::singleton($class);

            if (!$singleton->hasExtension(Versioned::class)) {
                // Skip non-versioned classes as there are no old version records to delete
                return false;
            }

            if ($class !== $singleton->baseClass()) {
                // Skip non-base-class as subclasses are covered automatically
                return false;
            }

            return true;
        });
    }

    /**
     * Determine which records have versions that can be deleted
     *
     * @param array $classes
     * @return array
     */
    public function getRecordsForDeletion(array $classes): array
    {
        $keepLimit = (int) $this->config()->get('keep_limit');
        $recordLimit = (int) $this->config()->get('deletion_record_limit');
        $deletionDate = DBDatetime::create_field('Datetime', DBDatetime::now()->Rfc2822())
            ->modify(sprintf('- %d days', $this->config()->get('keep_lifetime')))
            ->Rfc2822();
        $records = [];

        foreach ($classes as $class) {
            /** @var DataObject|FluentExtension $singleton */
            $singleton = DataObject::singleton($class);
            $isLocalised = $singleton->hasExtension(FluentVersionedExtension::class)
                && count($singleton->getLocalisedFields()) > 0;

            $mainTable = $this->getTableNameForClass($class);
            $baseTableRaw = $this->getVersionTableName($mainTable);
            $baseTable = sprintf('"%s"', $baseTableRaw);
            $query = SQLSelect::create(
                [
                    // We need to identify the records which have old versions ready for deletion
                    $baseTable . '."RecordID"',
                ],
                $baseTable,
                [
                    // Include only versions older than specified date
                    $baseTable . '."LastEdited" <= ?' => $deletionDate,
                    // Include only draft edits versions
                    // as we don't want to delete publish versions because these drive isPublishedInLocale()
                    $baseTable . '."WasPublished"' => 0,
                    // Skip records without mandatory data
                    $baseTable . '."ClassName" IS NOT NULL',
                    $baseTable . '."ClassName" != ?' => '',
                ],
                [
                    // Apply consistent ordering
                    $baseTable . '."RecordID"' => 'ASC',
                ],
                [
                    // Grouping by Record ID as we want to get RecordID overview at this point
                    $baseTable . '."RecordID"',
                ],
                [
                    // Need to have more old versions than the allowed limit
                    'COUNT(*) > ?' => $keepLimit,
                ],
                $recordLimit
            );

            // Localised objects need additional join
            if ($isLocalised) {
                $localisedTableRaw = $this->getVersionLocalisedTableName($mainTable);
                $localisedTable = sprintf('"%s"', $localisedTableRaw);
                $query
                    // Join through to the localised table
                    // Version numbers map one to one
                    ->addInnerJoin(
                        $localisedTableRaw,
                        sprintf(
                            '%1$s."RecordID" = %2$s."RecordID" AND %1$s."Version" = %2$s."Version"',
                            $baseTable,
                            $localisedTable
                        )
                    )
                    ->addSelect([
                        // We will need the locale information as well later on
                        $localisedTable . '."Locale"',
                    ])
                    ->addGroupBy([
                        // Grouping has to be extended to locales
                        // as we want to keep the minimum number of versions per locale
                        $localisedTable . '."Locale"',
                    ])
                    ->addOrderBy([
                        // Extends consistent ordering to locales
                        $localisedTable . '."Locale"',
                    ]);
            }

            $results = $query->execute();

            if ($results === null) {
                continue;
            }

            $data = [];

            while ($result = $results->next()) {
                $item = [
                    'id' => (int) $result['RecordID'],
                ];

                if ($isLocalised) {
                    // Add additional locale data for localised records
                    $item['locale'] = $result['Locale'];
                }

                $data[] = $item;
            }

            if (count($data) === 0) {
                continue;
            }

            $records[$class] = $data;
        }

        return $records;
    }

    /**
     * Determine which versions need to be deleted for specified records
     *
     * @param array $records
     * @return array
     */
    public function getVersionsForDeletion(array $records): array
    {
        $keepLimit = (int) $this->config()->get('keep_limit');
        $versionLimit = (int) $this->config()->get('deletion_version_limit');
        $deletionDate = DBDatetime::create_field('Datetime', DBDatetime::now()->Rfc2822())
            ->modify(sprintf('- %d days', $this->config()->get('keep_lifetime')))
            ->Rfc2822();
        $versions = [];

        foreach ($records as $baseClass => $items) {
            $mainTable = $this->getTableNameForClass($baseClass);
            $baseTableRaw = $this->getVersionTableName($mainTable);
            $baseTable = sprintf('"%s"', $baseTableRaw);

            foreach ($items as $item) {
                $recordId = $item['id'];
                $locale = array_key_exists('locale', $item)
                    ? $item['locale']
                    : null;

                $query = SQLSelect::create(
                    [
                        // We need version number so we can delete it
                        $baseTable . '."Version"',
                        // We need class name as this drives which tables need to be joined during deletion
                        $baseTable . '."ClassName"',
                    ],
                    $baseTable,
                    [
                        $baseTable . '."RecordID"' => $recordId,
                        // Include only versions older than specified date
                        $baseTable . '."LastEdited" <= ?' => $deletionDate,
                        // Include only draft edits versions
                        // as we don't want to delete publish versions because these drive isPublishedInLocale()
                        $baseTable . '."WasPublished"' => 0,
                        // Skip records without mandatory data
                        $baseTable . '."ClassName" IS NOT NULL',
                        $baseTable . '."ClassName" != ?' => '',
                    ],
                    [
                        // Latest versions first so we can keep the minimum required versions easily
                        // This will cause the newer versions to be deleted first but it shouldn't matter
                        // as all old versions will get deleted eventually (order shouldn't matter)
                        $baseTable . '."Version"' => 'DESC',
                    ],
                    [],
                    [],
                    [
                        // Make sure we skip the versions which need to be retained
                        // these will be at the start of the list because of our sorting order
                        'limit' => $versionLimit,
                        'start' => $keepLimit,
                    ]
                );

                // Localised objects need additional join
                if ($locale) {
                    $localisedTableRaw = $this->getVersionLocalisedTableName($mainTable);
                    $localisedTable = sprintf('"%s"', $localisedTableRaw);
                    $query
                        // Join through to the localised table
                        // Version numbers map one to one
                        ->addInnerJoin(
                            $localisedTableRaw,
                            sprintf(
                                '%1$s."RecordID" = %2$s."RecordID" AND %1$s."Version" = %2$s."Version"',
                                $baseTable,
                                $localisedTable
                            )
                        )
                        ->addWhere([
                            // Narrow down the search to specific locale, this ensures that we keep minimum
                            // required versions per locale
                            $localisedTable . '."Locale"' => $locale,
                        ]);
                }

                $results = $query->execute();

                if ($results === null) {
                    continue;
                }

                $data = [];

                // Group versions by class so it's easier to process them later
                while ($result = $results->next()) {
                    $version = (int) $result['Version'];
                    $class = $result['ClassName'];

                    if (!array_key_exists($class, $data)) {
                        $data[$class] = [];
                    }

                    $data[$class][] = $version;
                }

                if (count($data) === 0) {
                    continue;
                }

                $versions[$baseClass][$recordId] = $data;
            }
        }

        return $versions;
    }

    /**
     * Pack versions into jobs so we can delete them in smaller chunks
     *
     * @param array $groups
     * @throws ValidationException
     */
    public function queueDeletionJobsForVersionGroups(array $groups): void
    {
        // Format data into job specific format so it's easy to consume
        $data = [];

        foreach ($groups as $records) {
            foreach ($records as $recordId => $recordData) {
                foreach ($recordData as $class => $versions) {
                    $data[] = [
                        'id' => $recordId,
                        'class' => $class,
                        'versions' => $versions,
                    ];
                }
            }
        }

        if (count($data) === 0) {
            return;
        }

        $batchSize = (int) $this->config()->get('deletion_batch_size');
        $data = $batchSize > 0
            ? array_chunk($data, $batchSize)
            : [$data];

        $service = QueuedJobService::singleton();

        foreach ($data as $chunk) {
            $job = new CleanupJob();
            $job->hydrate($chunk);
            $service->queueJob($job);
        }
    }

    /**
     * Execute deletion of specified versions for a record
     *
     * @param string $class
     * @param int $recordId
     * @param array $versions
     * @return int
     */
    public function deleteVersions(string $class, int $recordId, array $versions): int
    {
        if (count($versions) === 0) {
            // Nothing to delete
            return 0;
        }

        $tables = $this->getTablesListForClass($class);
        $baseTables = $tables['base'];

        if (count($baseTables) === 0) {
            return 0;
        }

        // We can assume first table is the base table
        $baseTableRaw = $baseTables[0];
        $baseTablesRaw = $baseTables;

        $baseTable = sprintf('"%s"', $baseTableRaw);
        array_walk($baseTables, static function (&$item): void {
            $item = sprintf('"%s"', $item);
        });

        $query = SQLDelete::create(
            [
                $baseTable,
            ],
            [
                // We are deleting specific versions for specific record
                $baseTable . '."RecordID"' => $recordId,
                sprintf($baseTable . '."Version" IN (%s)', DB::placeholders($versions)) => $versions,
            ],
            $baseTables,
        );

        // Join additional tables so we can delete all related data (avoid orphaned version data)
        foreach ($baseTablesRaw as $table) {
            // No need to join the base table as it's already present in the FROM
            if ($table === $baseTableRaw) {
                continue;
            }

            $query->addLeftJoin(
                $table,
                sprintf('%1$s."RecordID" = "%2$s"."RecordID" AND %1$s."Version" = "%2$s"."Version"', $baseTable, $table)
            );
        }

        $localisedTables = $tables['localised'];

        // Add localised table to the join and deletion
        if (count($localisedTables) > 0) {
            $localisedTablesRaw = $localisedTables;

            array_walk($localisedTables, static function (&$item): void {
                $item = sprintf('"%s"', $item);
            });

            // Register localised tables for deletion so we delete records from it
            $query->addDelete($localisedTables);

            foreach ($localisedTablesRaw as $table) {
                $query->addLeftJoin(
                    $table,
                    sprintf(
                        '%1$s."RecordID" = "%2$s"."RecordID" AND %1$s."Version" = "%2$s"."Version"',
                        $baseTable,
                        $table
                    )
                );
            }
        }

        $results = $query->execute();

        if ($results === null) {
            return 0;
        }

        return DB::affected_rows();
    }

    /**
     * Get list of all tables that the specified class has
     *
     * @param string $class
     * @return array[]
     */
    protected function getTablesListForClass(string $class): array
    {
        /** @var DataObject|FluentExtension $singleton */
        $singleton = DataObject::singleton($class);
        $classes = ClassInfo::ancestry($class, true);
        $tables = [];

        foreach ($classes as $currentClass) {
            $tables[] = $this->getTableNameForClass($currentClass);
        }

        $baseTables = [];
        $localisedTables = [];

        foreach ($tables as $table) {
            $baseTables[] = $this->getVersionTableName($table);
        }

        // Include localised tables if needed
        if ($singleton->hasExtension(FluentVersionedExtension::class)) {
            $localisedDataTables = array_keys($singleton->getLocalisedTables());

            foreach ($tables as $table) {
                if (!in_array($table, $localisedDataTables)) {
                    // Skip any tables that do not contain localised data
                    continue;
                }

                $localisedTables[] = $this->getVersionLocalisedTableName($table);
            }
        }

        return [
            'base' => $baseTables,
            'localised' => $localisedTables,
        ];
    }

    /**
     * Determine name of table from class
     *
     * @param string $class
     * @return string
     */
    protected function getTableNameForClass(string $class): string
    {
        $table = DataObject::singleton($class)
            ->config()
            ->uninherited('table_name');

        // Fallback to class name if no table name is specified
        return $table ?: $class;
    }

    /**
     * Determine the name of version table
     *
     * @param string $table
     * @return string
     */
    protected function getVersionTableName(string $table): string
    {
        return $table . '_Versions';
    }

    /**
     * Determine the name of localised version table
     *
     * @param string $table
     * @return string
     */
    protected function getVersionLocalisedTableName(string $table): string
    {
        return $table . '_Localised_Versions';
    }
}
