<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\CacheService;
use Predis\Client;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for CacheService.
 * 
 * Tests the cache abstraction layer including:
 * - Redis primary cache operations
 * - Symfony Cache fallback mechanism
 * - Error handling and logging
 * - Cache availability detection
 * 
 * @covers CacheService
 */
class CacheServiceTest extends TestCase
{
    private CacheService $cacheService;
    private Client|MockObject $redisClient;
    private CacheInterface|MockObject $fallbackCache;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        // Create a mock with magic methods enabled for Predis Client
        $this->redisClient = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->fallbackCache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Setup default ping behavior for constructor
        $this->redisClient->method('__call')
            ->willReturnCallback(function ($method, $args) {
                if ($method === 'ping') return 'PONG';
                return null;
            });

        $this->cacheService = new CacheService(
            $this->redisClient,
            $this->fallbackCache,
            $this->logger
        );
    }

    public function testSetWithRedisSuccess(): void
    {
        // Arrange
        $key = 'test:key';
        $value = ['data' => 'test'];
        $ttl = 3600;
        
        // Create a fresh service for this test
        $redisClient = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $redisClient->method('__call')
            ->willReturnCallback(function ($method, $args) {
                if ($method === 'ping') return 'PONG';
                if ($method === 'setex') return 'OK';
                return null;
            });

        $fallbackCache = $this->createMock(CacheInterface::class);
        $fallbackCache->expects($this->once())
            ->method('delete')
            ->with($key);

        $fallbackCache->expects($this->once())
            ->method('get')
            ->with($key, $this->isType('callable'));

        $cacheService = new CacheService($redisClient, $fallbackCache, $this->logger);

        // Act
        $result = $cacheService->set($key, $value, $ttl);

        // Assert
        $this->assertTrue($result);
    }

    public function testGetWithRedisSuccess(): void
    {
        // Arrange
        $key = 'test:key';
        $value = ['data' => 'test'];
        $serializedValue = serialize($value);

        // Create a fresh service for this test
        $redisClient = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $redisClient->method('__call')
            ->willReturnCallback(function ($method, $args) use ($key, $serializedValue) {
                if ($method === 'ping') return 'PONG';
                if ($method === 'get' && $args[0] === $key) return $serializedValue;
                return null;
            });

        $cacheService = new CacheService($redisClient, $this->fallbackCache, $this->logger);

        // Act
        $result = $cacheService->get($key);

        // Assert
        $this->assertEquals($value, $result);
    }

    public function testGetWithRedisReturnsNull(): void
    {
        // Arrange
        $key = 'nonexistent:key';

        $this->redisClient->expects($this->once())
            ->method('__call')
            ->with('get', [$key])
            ->willReturn(null);

        $this->fallbackCache->expects($this->once())
            ->method('get')
            ->with($key, $this->isType('callable'))
            ->willReturn(null);

        // Act
        $result = $this->cacheService->get($key);

        // Assert
        $this->assertNull($result);
    }

    public function testDeleteWithRedisSuccess(): void
    {
        // Arrange
        $key = 'test:key';

        $this->redisClient->expects($this->once())
            ->method('__call')
            ->with('del', [$key])
            ->willReturn(1);

        $this->fallbackCache->expects($this->once())
            ->method('delete')
            ->with($key)
            ->willReturn(true);

        // Act
        $result = $this->cacheService->delete($key);

        // Assert
        $this->assertTrue($result);
    }

    public function testClearWithRedis(): void
    {
        // Arrange
        $pattern = 'currency_rate:*';
        $keys = ['currency_rate:USD_EUR', 'currency_rate:USD_GBP'];

        // Create a fresh service for this test
        $redisClient = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $redisClient->method('__call')
            ->willReturnCallback(function ($method, $args) use ($pattern, $keys) {
                if ($method === 'ping') return 'PONG';
                if ($method === 'keys' && $args[0] === $pattern) return $keys;
                if ($method === 'del' && $args[0] === $keys) return 2;
                return null;
            });

        $cacheService = new CacheService($redisClient, $this->fallbackCache, $this->logger);

        // Act
        $result = $cacheService->clear($pattern);

        // Assert
        $this->assertTrue($result);
    }

    public function testClearWithEmptyResult(): void
    {
        // Arrange
        $pattern = 'nonexistent:*';

        $this->redisClient->expects($this->once())
            ->method('__call')
            ->with('keys', [$pattern])
            ->willReturn([]);

        // Act
        $result = $this->cacheService->clear($pattern);

        // Assert
        $this->assertFalse($result);
    }

    public function testIsRedisAvailable(): void
    {
        // Act
        $result = $this->cacheService->isRedisAvailable();

        // Assert
        $this->assertTrue($result);
    }

    public function testGetStatsWithRedis(): void
    {
        // Arrange
        $redisInfo = [
            'connected_clients' => '10',
            'used_memory_human' => '2.5M',
            'keyspace_hits' => '1000',
            'keyspace_misses' => '50'
        ];

        // Create a fresh service for this test
        $redisClient = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $redisClient->method('__call')
            ->willReturnCallback(function ($method, $args) use ($redisInfo) {
                if ($method === 'ping') return 'PONG';
                if ($method === 'info') return $redisInfo;
                return null;
            });

        $cacheService = new CacheService($redisClient, $this->fallbackCache, $this->logger);

        // Act
        $result = $cacheService->getStats();

        // Assert
        $expected = [
            'redis_available' => true,
            'fallback_type' => 'symfony_filesystem',
            'redis_info' => [
                'connected_clients' => '10',
                'used_memory_human' => '2.5M',
                'keyspace_hits' => '1000',
                'keyspace_misses' => '50'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetStatsWithRedisUnavailable(): void
    {
        // Create a new service instance where Redis fails
        $failingRedisClient = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $failingRedisClient->method('__call')
            ->willReturnCallback(function ($method, $args) {
                if ($method === 'ping') {
                    // Create a mock connection to satisfy constructor
                    $connection = $this->createMock(\Predis\Connection\NodeConnectionInterface::class);
                    throw new \Predis\Connection\ConnectionException($connection, 'Redis connection failed');
                }
                return null;
            });

        $cacheService = new CacheService(
            $failingRedisClient,
            $this->fallbackCache,
            $this->logger
        );

        // Act
        $result = $cacheService->getStats();

        // Assert
        $this->assertEquals([
            'redis_available' => false,
            'fallback_type' => 'symfony_filesystem'
        ], $result);
    }
}