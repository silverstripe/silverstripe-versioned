<?php


namespace SilverStripe\Versioned\Caching;

use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Cache\DefaultCacheFactory;

/**
 * Allows injection of a psr-6 proxy over an inner cache backend.
 * You can pass in 'disable-container' to request a raw cache in a case-by-case basis
 */
class ProxyCacheFactory extends DefaultCacheFactory
{
    /**
     * Class name of a psr-16 cache
     *
     * @var string
     */
    protected $containerClass = null;

    public function create($service, array $args = [])
    {
        $backend = parent::create($service, $args);

        // Wrap with configured proxy
        $args = array_merge($this->args, $args);

        // Ensure container class is specified and isn't disabled for specific caches
        if (!empty($args['container']) && empty($args['disable-container'])) {
            $container = $args['container'];
            if (!is_a($container, CacheInterface::class, true)) {
                throw new InvalidArgumentException("\"{$container}\" is not a valid PSR-16 cache interface");
            }
            return $this->createCache($container, [$backend]);
        }

        return $backend;
    }
}
