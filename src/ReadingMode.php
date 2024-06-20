<?php

namespace SilverStripe\Versioned;

use InvalidArgumentException;

/**
 * Converter helpers for versioned args
 */
class ReadingMode
{
    /**
     * Convert reading mode string to dataquery params.
     * Only supports stage / archive
     *
     * @param string $mode Reading mode string
     * @return array|null
     */
    public static function toDataQueryParams($mode)
    {
        if (empty($mode)) {
            return null;
        }
        if (!is_string($mode)) {
            throw new InvalidArgumentException("mode must be a string");
        }
        $parts = explode('.', $mode ?? '');
        switch ($parts[0]) {
            case 'Archive':
                $archiveStage = isset($parts[2]) ? $parts[2] : Versioned::DRAFT;
                ReadingMode::validateStage($archiveStage);
                return [
                    'Versioned.mode' => 'archive',
                    'Versioned.date' => $parts[1],
                    'Versioned.stage' => $archiveStage,
                ];
            case 'Stage':
                ReadingMode::validateStage($parts[1]);
                return [
                    'Versioned.mode' => 'stage',
                    'Versioned.stage' => $parts[1],
                ];
            default:
                // Unsupported mode
                return null;
        }
    }

    /**
     * Converts dataquery params to original reading mode.
     * Only supports stage / archive
     *
     * @param array $params
     * @return string|null
     */
    public static function fromDataQueryParams($params)
    {
        // Switch on reading mode
        if (empty($params["Versioned.mode"])) {
            return null;
        }

        switch ($params["Versioned.mode"]) {
            case 'archive':
                return 'Archive.' . $params['Versioned.date'] . '.' . $params['Versioned.stage'];
            case 'stage':
                return 'Stage.' . $params['Versioned.stage'];
            default:
                return null;
        }
    }

    /**
     * Convert querystring arguments to reading mode.
     * Only supports stage / archive mode
     *
     * @param array|string $query Querystring arguments (array or string)
     * @return string|null Reading mode, or null if not found / supported
     */
    public static function fromQueryString($query)
    {
        if (is_string($query)) {
            parse_str($query ?? '', $query);
        }
        if (empty($query)) {
            return null;
        }
        // Check date
        $archiveDate = isset($query['archiveDate']) && strtotime($query['archiveDate'] ?? '')
            ? $query['archiveDate']
            : null;

        // Check stage (ignore invalid stages)
        $stage = null;
        if (isset($query['stage']) && strcasecmp($query['stage'] ?? '', Versioned::DRAFT ?? '') === 0) {
            $stage = Versioned::DRAFT;
        } elseif (isset($query['stage']) && strcasecmp($query['stage'] ?? '', Versioned::LIVE ?? '') === 0) {
            $stage = Versioned::LIVE;
        }

        // Archive date is specified
        if ($archiveDate) {
            // Stage defaults to draft archive
            $stage = $stage ?: Versioned::DRAFT;
            return 'Archive.' . $archiveDate . '.' . $stage;
        }

        // Stage is specified by itself
        if ($stage) {
            return 'Stage.' . $stage;
        }

        // Unsupported query mode
        return null;
    }

    /**
     * Build querystring arguments for current reading mode.
     * Supports stage / archive only.
     *
     * @param string $mode
     * @return array List of querystring arguments as an arry
     */
    public static function toQueryString($mode)
    {
        if (empty($mode)) {
            return null;
        }
        if (!is_string($mode)) {
            throw new InvalidArgumentException("mode must be a string");
        }
        $parts = explode('.', $mode ?? '');
        switch ($parts[0]) {
            case 'Archive':
                $archiveStage = isset($parts[2]) ? $parts[2] : Versioned::DRAFT;
                ReadingMode::validateStage($archiveStage);
                return [
                    'archiveDate' => $parts[1],
                    'stage' => $archiveStage,
                ];
            case 'Stage':
                ReadingMode::validateStage($parts[1]);
                return [
                    'stage' => $parts[1],
                ];
            default:
                // Unsupported mode
                return null;
        }
    }

    /**
     * Validate the stage is valid, throwing an exception if it's not
     *
     * @param string $stage
     */
    public static function validateStage($stage)
    {
        if (!in_array($stage, [Versioned::LIVE, Versioned::DRAFT])) {
            throw new InvalidArgumentException("Invalid stage name \"{$stage}\"");
        }
    }
}
