<?php

namespace SilverStripe\GraphQL\Resolvers;

use SilverStripe\Dev\Deprecation;
use SilverStripe\GraphQL\Scaffolding\StaticSchema;
use SilverStripe\ORM\DataList;
use SilverStripe\Versioned\Versioned;
use InvalidArgumentException;
use DateTime;

// GraphQL dependency is optional in versioned,
// and legacy implementation relies on existence of this class (in GraphQL v3)
if (!class_exists(Manager::class)) {
    return;
}

/**
 * @deprecated 1.8.0 Use the latest version of graphql instead
 */
class ApplyVersionFilters
{
    /**
     * Use this as a fallback where resolver results aren't queried as a DataList,
     * but rather use DataObject::get_one(). Example: SiteTree::get_by_link().
     * Note that the 'status' and 'version' modes are not supported.
     * Wrap this call in {@link Versioned::withVersionedMode()} in order to avoid side effects.
     *
     * @param $versioningArgs
     */
    public function __construct()
    {
        Deprecation::notice('1.8.0', 'Use the latest version of graphql instead', Deprecation::SCOPE_CLASS);
    }

    public function applyToReadingState($versioningArgs)
    {
        list ($mode, $archiveDate) = StaticSchema::inst()->extractKeys(
            ['Mode', 'ArchiveDate'],
            $versioningArgs
        );
        if (!$mode) {
            return;
        }

        $this->validateArgs($versioningArgs);

        switch ($mode) {
            case Versioned::LIVE:
            case Versioned::DRAFT:
            case 'latest_versions':
            case 'all_versions':
                Versioned::set_stage($mode);
                break;
            case 'archive':
                $date = $archiveDate;
                Versioned::set_reading_mode($mode);
                Versioned::reading_archived_date($date);
                break;
            case 'status':
                throw new InvalidArgumentException(
                    'The "status" mode is not supported for setting versioned reading stages'
                );
                break;
            case 'version':
                throw new InvalidArgumentException(
                    'The "version" mode is not supported for setting versioned reading stages'
                );
                break;
            default:
                throw new InvalidArgumentException("Unsupported read mode {$mode}");
        }
    }

    /**
     * @param DataList $list
     * @param array $versioningArgs
     */
    public function applyToList(&$list, $versioningArgs)
    {
        list ($mode, $date, $statuses, $version) = StaticSchema::inst()->extractKeys(
            ['Mode', 'ArchiveDate', 'Status', 'Version'],
            $versioningArgs
        );

        if (!$mode) {
            return;
        }

        $this->validateArgs($versioningArgs);

        switch ($mode) {
            case Versioned::LIVE:
            case Versioned::DRAFT:
                $list = $list
                    ->setDataQueryParam('Versioned.mode', 'stage')
                    ->setDataQueryParam('Versioned.stage', $mode);
                break;
            case 'archive':
                $list = $list
                    ->setDataQueryParam('Versioned.mode', 'archive')
                    ->setDataQueryParam('Versioned.date', $date);
                break;
            case 'all_versions':
                $list = $list->setDataQueryParam('Versioned.mode', 'all_versions');
                break;
            case 'latest_versions':
                $list = $list->setDataQueryParam('Versioned.mode', 'latest_versions');
                break;
            case 'status':
                // When querying by Status we need to ensure both stage / live tables are present
                $baseTable = singleton($list->dataClass())->baseTable();
                $liveTable = $baseTable . '_Live';

                // If we need to search archived records, we need to manually join draft table
                if (in_array('archived', $statuses)) {
                    $list = $list
                        ->setDataQueryParam('Versioned.mode', 'latest_versions');
                    // Join a temporary alias BaseTable_Draft, renaming this on execution to BaseTable
                    // See Versioned::augmentSQL() For reference on this alias
                    $draftTable = $baseTable . '_Draft';
                    $list = $list
                        ->leftJoin(
                            $draftTable,
                            "\"{$baseTable}\".\"ID\" = \"{$draftTable}\".\"ID\""
                        );
                } else {
                    // Use draft as base query mode (base join live)
                    $draftTable = $baseTable;
                    $list = $list
                        ->setDataQueryParam('Versioned.mode', 'stage')
                        ->setDataQueryParam('Versioned.stage', Versioned::DRAFT);
                }

                // Always include live table
                $list = $list->leftJoin(
                    $liveTable,
                    "\"{$baseTable}\".\"ID\" = \"{$liveTable}\".\"ID\""
                );

                // Add all conditions
                $conditions = [];

                // Modified exist on both stages, but differ
                if (in_array('modified', $statuses)) {
                    $conditions[] = "\"{$liveTable}\".\"ID\" IS NOT NULL AND \"{$draftTable}\".\"ID\" IS NOT NULL"
                        . " AND \"{$draftTable}\".\"Version\" <> \"{$liveTable}\".\"Version\"";
                }

                // Is deleted and sent to archive
                if (in_array('archived', $statuses)) {
                    // Note: Include items staged for deletion for the time being, as these are effectively archived
                    // we could split this out into "staged for deletion" in the future
                    $conditions[] = "\"{$draftTable}\".\"ID\" IS NULL";
                }

                // Is on draft only
                if (in_array('draft', $statuses)) {
                    $conditions[] = "\"{$liveTable}\".\"ID\" IS NULL AND \"{$draftTable}\".\"ID\" IS NOT NULL";
                }

                if (in_array('published', $statuses)) {
                    $conditions[] = "\"{$liveTable}\".\"ID\" IS NOT NULL";
                }

                // Validate that all statuses have been handled
                if (empty($conditions) || count($statuses) !== count($conditions)) {
                    throw new InvalidArgumentException("Invalid statuses provided");
                }
                $list = $list->whereAny(array_filter($conditions));
                break;
            case 'version':
                // Note: Only valid for ReadOne
                $list = $list->setDataQueryParam([
                    "Versioned.mode" => 'version',
                    "Versioned.version" => $version,
                ]);
                break;
            default:
                throw new InvalidArgumentException("Unsupported read mode {$mode}");
        }
    }

    /**
     * @throws InvalidArgumentException
     * @param $versioningArgs
     */
    public function validateArgs($versioningArgs)
    {
        list ($mode, $date, $status, $version) = StaticSchema::inst()->extractKeys(
            ['Mode', 'ArchiveDate', 'Status', 'Version'],
            $versioningArgs
        );

        switch ($mode) {
            case Versioned::LIVE:
            case Versioned::DRAFT:
                break;
            case 'archive':
                if (empty($date)) {
                    throw new InvalidArgumentException(sprintf(
                        'You must provide an ArchiveDate parameter when using the "%s" mode',
                        $mode
                    ));
                }
                if (!$this->isValidDate($date)) {
                    throw new InvalidArgumentException(sprintf(
                        'Invalid date: "%s". Must be YYYY-MM-DD format',
                        $date
                    ));
                }
                break;
            case 'all_versions':
                break;
            case 'latest_versions':
                break;
            case 'status':
                if (empty($status)) {
                    throw new InvalidArgumentException(sprintf(
                        'You must provide a Status parameter when using the "%s" mode',
                        $mode
                    ));
                }
                break;
            case 'version':
                // Note: Only valid for ReadOne
                if ($version === null) {
                    throw new InvalidArgumentException(
                        'When using the "version" mode, you must specify a Version parameter'
                    );
                }
                break;
            default:
                throw new InvalidArgumentException("Unsupported read mode {$mode}");
        }
    }

    /**
     * Returns true if date is in proper YYYY-MM-DD format
     * @param string $date
     * @return bool
     */
    protected function isValidDate($date)
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);

        return ($dt !== false && !array_sum($dt->getLastErrors()));
    }
}
