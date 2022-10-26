<?php

namespace SilverStripe\Versioned\Caching;

use Psr\Log\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Traversable;

/**
 * psr-6 cache proxy for an internal cache, which provides segmentation of
 * cache keys based on current versioned mode. This ensures that cross-stage
 * content cannot cross-pollenate each other.
 *
 * Note: segmentation can be disabled via 'versionedstate = false' being supplied as a
 * constructor arg.
 *
 * Based on Symfony\Component\Cache\Simple\TraceableCache
 */
abstract class ProxyCacheAdapter implements CacheInterface, ResettableInterface, PruneableInterface
{
    /**
     * @var CacheInterface Backend pool
     */
    protected $pool;

    /**
     * Create container cache controlling an inner pool cache
     *
     * @param CacheInterface $pool
     */
    public function __construct(CacheInterface $pool)
    {
        $this->pool = $pool;
    }

    /**
     * Do not serialize() `$this->pool` because it may contain a non-serializable cache.
     * For instance, Symfony\Component\Cache\Simple\FilesystemCache cannot be serialized because in will throw an
     * exception in Symfony\Component\Cache\Traits\FilesystemCommonTrait::__sleep()
     *
     * @return array
     */
    public function __sleep()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null): mixed
    {
        $keyID = $this->getKeyID($key);
        return $this->pool->get($keyID, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $keyID = $this->getKeyID($key);
        return $this->pool->has($keyID);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $keyID = $this->getKeyID($key);
        return $this->pool->delete($keyID);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, $ttl = null): bool
    {
        $keyID = $this->getKeyID($key);
        return $this->pool->set($keyID, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $items, $ttl = null): bool
    {
        // Map associative item keys to ids (safely casting as array as byproduct)
        $itemsByID = [];
        foreach ($items as $key => $value) {
            $keyID = $this->getKeyID($key);
            $itemsByID[$keyID] = $value;
        }

        // Pass back
        return $this->pool->setMultiple($itemsByID, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, $default = null): iterable
    {
        $keys = $this->iteratorToArray($keys);
        $keyIDs = $this->getKeyIDs($keys);

        // Delegate to pool
        $itemsByID = $this->pool->getMultiple($keyIDs, $default);

        // Enforce $poolResult is same length / order as $keyIDs prior to combining back
        $items = array_map(function ($keyID) use ($default, $itemsByID) {
            return isset($itemsByID[$keyID]) ? $itemsByID[$keyID] : $default;
        }, $keyIDs ?? []);

        // Combine back with original keys
        return array_combine($keys ?? [], $items ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        return $this->pool->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $keyIDs = $this->getKeyIDs($keys);
        return $this->pool->deleteMultiple($keyIDs);
    }

    /**
     * {@inheritdoc}
     */
    public function prune(): bool
    {
        if ($this->pool instanceof PruneableInterface) {
            return $this->pool->prune();
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        if ($this->pool instanceof ResettableInterface) {
            $this->pool->reset();
        }
    }

    /**
     * Map user cache to internal cache
     *
     * @param string $key
     * @return string
     */
    abstract protected function getKeyID($key);

    /**
     * Get key ids
     *
     * @param iterable $keys
     * @return array Array where keys are passed in $keys, and values are key IDs
     */
    protected function getKeyIDs($keys)
    {
        // Force iterator to array with simple temp array
        $map = [];
        foreach ($keys as $key) {
            $map[] = $this->getKeyID($key);
        }
        return $map;
    }

    /**
     * Ensure that a list is cast to an array
     *
     * @param iterable $keys
     * @return array
     */
    protected function iteratorToArray($keys)
    {
        // Already array
        if (is_array($keys)) {
            return $keys;
        }

        // Handle iterable
        if ($keys instanceof Traversable) {
            return iterator_to_array($keys, false);
        }

        // Error
        $keysType = is_object($keys) ? get_class($keys) : gettype($keys);
        throw new InvalidArgumentException("Cache keys must be array or Traversable, \"{$keysType}\" given");
    }
}
