<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Api\V1;

use App\Entity\CurrencyRate;
use App\Repository\CurrencyRateRepository;
use App\Service\Api\V1\CurrencyService;
use App\Service\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for CurrencyService.
 * 
 * Tests currency conversion logic including:
 * - Cache-aside pattern implementation
 * - Direct and reverse currency conversions
 * - Exchange rate calculations
 * - Cache management for currency rates
 * 
 * @covers CurrencyService
 */
class CurrencyServiceTest extends TestCase
{
    private CurrencyService $currencyService;
    private EntityManagerInterface|MockObject $entityManager;
    private CacheService|MockObject $cacheService;
    private LoggerInterface|MockObject $logger;
    private CurrencyRateRepository|MockObject $currencyRateRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cacheService = $this->createMock(CacheService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->currencyRateRepository = $this->createMock(CurrencyRateRepository::class);

        $this->entityManager->method('getRepository')
            ->willReturn($this->currencyRateRepository);

        $this->currencyService = new CurrencyService(
            $this->entityManager,
            $this->cacheService,
            $this->logger
        );
    }

    public function testConvertAmountSameCurrency(): void
    {
        // Arrange
        $amount = '100.00';
        $currency = 'USD';

        // Act
        $result = $this->currencyService->convertAmount($amount, $currency, $currency);

        // Assert
        $this->assertEquals([
            'converted_amount' => $amount,
            'exchange_rate' => '1.0'
        ], $result);
    }

    public function testConvertAmountWithCacheHit(): void
    {
        // Arrange
        $amount = '100.00';
        $fromCurrency = 'USD';
        $toCurrency = 'EUR';
        $exchangeRate = 0.92;
        $expectedConvertedAmount = '92.00';

        $this->cacheService->expects($this->once())
            ->method('get')
            ->with('currency_rate:USD_EUR')
            ->willReturn($exchangeRate);

        // Act
        $result = $this->currencyService->convertAmount($amount, $fromCurrency, $toCurrency);

        // Assert
        $this->assertEquals([
            'converted_amount' => $expectedConvertedAmount,
            'exchange_rate' => (string) $exchangeRate
        ], $result);
    }

    public function testConvertAmountWithCacheMissAndDbHit(): void
    {
        // Arrange
        $amount = '100.00';
        $fromCurrency = 'USD';
        $toCurrency = 'EUR';
        $exchangeRate = 0.92;
        $expectedConvertedAmount = '92.00';

        $currencyRate = $this->createMockCurrencyRate($fromCurrency, $toCurrency, $exchangeRate);

        $this->cacheService->expects($this->once())
            ->method('get')
            ->with('currency_rate:USD_EUR')
            ->willReturn(null); // Cache miss

        $this->currencyRateRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'baseCurrency' => $fromCurrency,
                'targetCurrency' => $toCurrency
            ])
            ->willReturn($currencyRate);

        $this->cacheService->expects($this->once())
            ->method('set')
            ->with('currency_rate:USD_EUR', $exchangeRate, 3600);

        // Act
        $result = $this->currencyService->convertAmount($amount, $fromCurrency, $toCurrency);

        // Assert
        $this->assertEquals([
            'converted_amount' => $expectedConvertedAmount,
            'exchange_rate' => (string) $exchangeRate
        ], $result);
    }

    public function testConvertAmountWithReverseConversion(): void
    {
        // Arrange
        $amount = '100.00';
        $fromCurrency = 'USD';
        $toCurrency = 'EUR';
        $reverseRate = 1.087; // EUR to USD
        $expectedRate = 1.0 / $reverseRate; // USD to EUR
        $expectedConvertedAmount = number_format((float) $amount * $expectedRate, 2, '.', '');

        $reverseCurrencyRate = $this->createMockCurrencyRate($toCurrency, $fromCurrency, $reverseRate);

        $this->cacheService->expects($this->once())
            ->method('get')
            ->willReturn(null); // Cache miss

        $this->currencyRateRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(null, $reverseCurrencyRate); // Direct miss, reverse hit

        $this->cacheService->expects($this->once())
            ->method('set');

        // Act
        $result = $this->currencyService->convertAmount($amount, $fromCurrency, $toCurrency);

        // Assert
        $this->assertEquals($expectedConvertedAmount, $result['converted_amount']);
        $this->assertEquals((string) $expectedRate, $result['exchange_rate']);
    }

    public function testConvertAmountWithNoRateFound(): void
    {
        // Arrange
        $amount = '100.00';
        $fromCurrency = 'USD';
        $toCurrency = 'XYZ'; // Non-existent currency

        $this->cacheService->expects($this->once())
            ->method('get')
            ->willReturn(null); // Cache miss

        $this->currencyRateRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturn(null); // Both direct and reverse miss

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No exchange rate found for USD to XYZ');
        $this->currencyService->convertAmount($amount, $fromCurrency, $toCurrency);
    }

    public function testGetExchangeRate(): void
    {
        // Arrange
        $fromCurrency = 'USD';
        $toCurrency = 'EUR';
        $exchangeRate = 0.92;

        $this->cacheService->expects($this->once())
            ->method('get')
            ->willReturn($exchangeRate);

        // Act
        $result = $this->currencyService->getExchangeRate($fromCurrency, $toCurrency);

        // Assert
        $this->assertEquals((string) $exchangeRate, $result);
    }

    public function testClearRateCacheSpecificPair(): void
    {
        // Arrange
        $fromCurrency = 'USD';
        $toCurrency = 'EUR';

        $this->cacheService->expects($this->once())
            ->method('delete')
            ->with('currency_rate:USD_EUR')
            ->willReturn(true);

        // Act
        $result = $this->currencyService->clearRateCache($fromCurrency, $toCurrency);

        // Assert
        $this->assertTrue($result);
    }

    public function testClearRateCacheAllRates(): void
    {
        // Arrange
        $this->cacheService->expects($this->once())
            ->method('clear')
            ->with('currency_rate:*')
            ->willReturn(true); // Cache cleared successfully

        // Act
        $result = $this->currencyService->clearRateCache();

        // Assert
        $this->assertTrue($result);
    }

    public function testGenerateCacheKey(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->currencyService);
        $method = $reflection->getMethod('generateCacheKey');
        $method->setAccessible(true);

        $cacheKey = $method->invoke($this->currencyService, 'usd', 'eur');

        $this->assertEquals('currency_rate:USD_EUR', $cacheKey);
    }

    private function createMockCurrencyRate(string $baseCurrency, string $targetCurrency, float $rate): CurrencyRate|MockObject
    {
        $currencyRate = $this->createMock(CurrencyRate::class);
        $currencyRate->method('getBaseCurrency')->willReturn($baseCurrency);
        $currencyRate->method('getTargetCurrency')->willReturn($targetCurrency);
        $currencyRate->method('getRate')->willReturn((string) $rate);

        return $currencyRate;
    }
}