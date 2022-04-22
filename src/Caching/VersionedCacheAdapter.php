<?php


namespace SilverStripe\Versioned\Caching;

use SilverStripe\Versioned\Versioned;

class VersionedCacheAdapter extends ProxyCacheAdapter
{
    /**
     * Ensure keys are segmented based on reading mode
     *
     * @param string $key
     * @return string
     */
    protected function getKeyID($key)
    {
        $state = Versioned::get_reading_mode();
        if ($state) {
            return $key . '_' . md5($state ?? '');
        }
        return $key;
    }
}
