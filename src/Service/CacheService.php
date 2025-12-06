<?php

declare(strict_types=1);

namespace App\Service;

use Predis\Client as RedisClient;
use Predis\Connection\ConnectionException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

/**
 * Cache service with Redis primary and Symfony Cache fallback strategy.
 * 
 * Implements a robust caching layer that automatically falls back to 
 * Symfony's file-based cache when Redis is unavailable. This ensures
 * the application continues to function even when Redis is down.
 * 
 * Features:
 * - Redis as primary cache for optimal performance
 * - Automatic fallback to Symfony Cache when Redis unavailable
 * - Cache-aside pattern implementation
 * - Comprehensive error handling and logging
 * - Flexible TTL management
 * 
 * @author MoneyMove API Team
 * @version 1.0.0
 * @since 2025-12-06
 */
class CacheService
{
    private bool $redisAvailable = true;
    private const DEFAULT_TTL = 1800; // 30 minutes
    
    public function __construct(
        private readonly RedisClient $redisClient,
        private readonly CacheInterface $fallbackCache,
        private readonly LoggerInterface $logger
    ) {
        $this->checkRedisAvailability();
    }

    /**
     * Get a cached value by key.
     * 
     * Implements cache-aside pattern:
     * 1. Try Redis first (if available)
     * 2. Fall back to Symfony Cache
     * 3. Return null if not found in either
     * 
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found
     */
    public function get(string $key): mixed
    {
        // Try Redis first if available
        if ($this->redisAvailable) {
            try {
                $value = $this->redisClient->get($key);
                if ($value !== null) {
                    $this->logger->debug('Cache hit from Redis', ['key' => $key]);
                    return $this->unserializeValue($value);
                }
                $this->logger->debug('Cache miss from Redis', ['key' => $key]);
            } catch (ConnectionException $e) {
                $this->handleRedisFailure($e);
                // Continue to fallback cache
            }
        }

        // Try fallback cache
        try {
            return $this->fallbackCache->get($key, function (ItemInterface $item) {
                $this->logger->debug('Cache miss from fallback', ['key' => $item->getKey()]);
                return null; // Return null to indicate cache miss
            });
        } catch (\Exception $e) {
            $this->logger->error('Fallback cache get failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Store a value in cache with TTL.
     * 
     * Stores in both Redis (if available) and fallback cache
     * to ensure consistency across cache layers.
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null = default TTL)
     * @return bool True if stored successfully in at least one cache
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? self::DEFAULT_TTL;
        $serializedValue = $this->serializeValue($value);
        $success = false;

        // Try Redis first
        if ($this->redisAvailable) {
            try {
                $result = $this->redisClient->setex($key, $ttl, $serializedValue);
                if ($result) {
                    $success = true;
                    $this->logger->debug('Value cached in Redis', [
                        'key' => $key,
                        'ttl' => $ttl
                    ]);
                }
            } catch (ConnectionException $e) {
                $this->handleRedisFailure($e);
                // Continue to fallback cache
            }
        }

        // Store in fallback cache
        try {
            $this->fallbackCache->delete($key); // Clear existing
            $this->fallbackCache->get($key, function (ItemInterface $item) use ($value, $ttl) {
                $item->expiresAfter($ttl);
                return $value;
            });
            $success = true;
            $this->logger->debug('Value cached in fallback', [
                'key' => $key,
                'ttl' => $ttl
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Fallback cache set failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }

        return $success;
    }

    /**
     * Delete a cached value by key.
     * 
     * Removes from both Redis and fallback cache to ensure
     * complete invalidation.
     * 
     * @param string $key Cache key
     * @return bool True if deleted from at least one cache
     */
    public function delete(string $key): bool
    {
        $success = false;

        // Delete from Redis
        if ($this->redisAvailable) {
            try {
                $result = $this->redisClient->del($key);
                if ($result > 0) {
                    $success = true;
                    $this->logger->debug('Key deleted from Redis', ['key' => $key]);
                }
            } catch (ConnectionException $e) {
                $this->handleRedisFailure($e);
            }
        }

        // Delete from fallback cache
        try {
            $result = $this->fallbackCache->delete($key);
            if ($result) {
                $success = true;
                $this->logger->debug('Key deleted from fallback', ['key' => $key]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Fallback cache delete failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }

        return $success;
    }

    /**
     * Clear all cached values matching a pattern.
     * 
     * @param string $pattern Pattern to match (Redis style pattern)
     * @return bool True if operation completed
     */
    public function clear(string $pattern = '*'): bool
    {
        $success = false;

        // Clear from Redis
        if ($this->redisAvailable) {
            try {
                $keys = $this->redisClient->keys($pattern);
                if (!empty($keys)) {
                    $this->redisClient->del($keys);
                    $success = true;
                    $this->logger->info('Keys cleared from Redis', [
                        'pattern' => $pattern,
                        'count' => count($keys)
                    ]);
                }
            } catch (ConnectionException $e) {
                $this->handleRedisFailure($e);
            }
        }

        // Note: Symfony Cache doesn't support pattern clearing easily
        // This would require implementing a custom approach if needed
        
        return $success;
    }

    /**
     * Check if Redis is currently available.
     * 
     * @return bool True if Redis is available
     */
    public function isRedisAvailable(): bool
    {
        return $this->redisAvailable;
    }

    /**
     * Get cache statistics and health information.
     * 
     * @return array Cache status information
     */
    public function getStats(): array
    {
        $stats = [
            'redis_available' => $this->redisAvailable,
            'fallback_type' => 'symfony_filesystem'
        ];

        if ($this->redisAvailable) {
            try {
                $info = $this->redisClient->info();
                $stats['redis_info'] = [
                    'connected_clients' => $info['connected_clients'] ?? 'unknown',
                    'used_memory_human' => $info['used_memory_human'] ?? 'unknown',
                    'keyspace_hits' => $info['keyspace_hits'] ?? 'unknown',
                    'keyspace_misses' => $info['keyspace_misses'] ?? 'unknown'
                ];
            } catch (\Exception $e) {
                $stats['redis_error'] = $e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * Check Redis availability and update status.
     */
    private function checkRedisAvailability(): void
    {
        try {
            $this->redisClient->ping();
            $this->redisAvailable = true;
            $this->logger->info('Redis connection established');
        } catch (ConnectionException $e) {
            $this->redisAvailable = false;
            $this->logger->warning('Redis not available, using fallback cache', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle Redis connection failures.
     */
    private function handleRedisFailure(ConnectionException $e): void
    {
        $this->redisAvailable = false;
        $this->logger->error('Redis connection failed, switching to fallback', [
            'error' => $e->getMessage()
        ]);
    }

    /**
     * Serialize value for storage in Redis.
     */
    private function serializeValue(mixed $value): string
    {
        return serialize($value);
    }

    /**
     * Unserialize value retrieved from Redis.
     */
    private function unserializeValue(string $value): mixed
    {
        return unserialize($value);
    }
}