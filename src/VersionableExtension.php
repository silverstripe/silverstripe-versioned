<?php

namespace SilverStripe\Versioned;

/**
 * Minimum level extra fields required by extensions that are versonable
 */
interface VersionableExtension
{
    /**
     * Determine if the given table is versionable
     *
     * @param string $table
     * @return bool True if versioned tables should be built for the given suffix
     */
    public function isVersionedTable($table);

    /**
     * Update fields and indexes for the versonable suffix table
     *
     * @param string $suffix Table suffix being built
     * @param array $fields List of fields in this model
     * @param array $indexes List of indexes in this model
     */
    public function updateVersionableFields($suffix, &$fields, &$indexes);

    /**
     * Modify table name with suffix.
     * Should return $table if not modified.
     *
     * @param string $table
     * @return string
     */
    public function extendWithSuffix($table);
}
