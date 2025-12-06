<?php

declare(strict_types=1);

namespace App\Service\Api\V1;

use Doctrine\ORM\EntityManagerInterface;
use App\Service\CacheService;
use Psr\Log\LoggerInterface;

/**
 * Service for handling currency conversion operations with caching.
 * 
 * Provides currency conversion functionality using exchange rates
 * stored in the database. Supports both direct and inverse conversions.
 * Implements cache-aside pattern for optimal performance:
 * - Check cache first for exchange rates
 * - Fetch from database on cache miss
 * - Store results in cache for future requests
 * 
 * @author MoneyMove API Team
 * @version 1.0.0
 * @since 2025-12-06
 */
class CurrencyService
{
    private const CACHE_TTL_RATES = 3600; // 1 hour
    private const CACHE_KEY_PREFIX = 'currency_rate';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheService $cacheService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Convert amount from one currency to another using cache-aside pattern.
     * 
     * @param string $amount Amount to convert
     * @param string $fromCurrency Source currency code (e.g., 'USD')
     * @param string $toCurrency Target currency code (e.g., 'EUR')
     * 
     * @return array{converted_amount: string, exchange_rate: string}
     * 
     * @throws \RuntimeException If no exchange rate is found
     */
    public function convertAmount(string $amount, string $fromCurrency, string $toCurrency): array
    {
        if ($fromCurrency === $toCurrency) {
            return [
                'converted_amount' => $amount,
                'exchange_rate' => '1.0'
            ];
        }

        // Get exchange rate using cache-aside pattern
        $exchangeRate = $this->getExchangeRateWithCache($fromCurrency, $toCurrency);
        
        if ($exchangeRate === null) {
            throw new \RuntimeException("No exchange rate found for {$fromCurrency} to {$toCurrency}");
        }

        // Calculate converted amount
        $convertedAmount = number_format((float) $amount * $exchangeRate, 2, '.', '');
        
        $this->logger->debug('Currency conversion completed', [
            'from_currency' => $fromCurrency,
            'to_currency' => $toCurrency,
            'original_amount' => $amount,
            'converted_amount' => $convertedAmount,
            'exchange_rate' => $exchangeRate,
            'cache_used' => $this->cacheService->isRedisAvailable()
        ]);

        return [
            'converted_amount' => $convertedAmount,
            'exchange_rate' => (string) $exchangeRate
        ];
    }

    /**
     * Get current exchange rate between two currencies.
     * 
     * @param string $fromCurrency Source currency
     * @param string $toCurrency Target currency
     * 
     * @return string Exchange rate as string
     * 
     * @throws \RuntimeException If no exchange rate is found
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): string
    {
        $rate = $this->getExchangeRateWithCache($fromCurrency, $toCurrency);
        
        if ($rate === null) {
            throw new \RuntimeException("No exchange rate found for {$fromCurrency} to {$toCurrency}");
        }
        
        return (string) $rate;
    }

    /**
     * Get exchange rate using cache-aside pattern.
     * 
     * Implementation:
     * 1. Check cache first
     * 2. If cache miss, fetch from database
     * 3. Store result in cache for future requests
     * 4. Return rate or null if not found
     * 
     * @param string $fromCurrency Source currency
     * @param string $toCurrency Target currency
     * 
     * @return float|null Exchange rate or null if not found
     */
    private function getExchangeRateWithCache(string $fromCurrency, string $toCurrency): ?float
    {
        // Generate cache key
        $cacheKey = $this->generateCacheKey($fromCurrency, $toCurrency);
        
        // Step 1: Check cache first
        $cachedRate = $this->cacheService->get($cacheKey);
        if ($cachedRate !== null) {
            $this->logger->debug('Exchange rate found in cache', [
                'cache_key' => $cacheKey,
                'rate' => $cachedRate
            ]);
            return (float) $cachedRate;
        }

        // Step 2: Cache miss - fetch from database
        $this->logger->debug('Exchange rate cache miss, fetching from database', [
            'cache_key' => $cacheKey
        ]);
        
        $rate = $this->fetchExchangeRateFromDatabase($fromCurrency, $toCurrency);
        
        if ($rate !== null) {
            // Step 3: Store in cache for future requests
            $this->cacheService->set($cacheKey, $rate, self::CACHE_TTL_RATES);
            $this->logger->debug('Exchange rate cached', [
                'cache_key' => $cacheKey,
                'rate' => $rate,
                'ttl' => self::CACHE_TTL_RATES
            ]);
        }

        return $rate;
    }

    /**
     * Fetch exchange rate from database with direct and reverse lookup.
     * 
     * @param string $fromCurrency Source currency
     * @param string $toCurrency Target currency
     * 
     * @return float|null Exchange rate or null if not found
     */
    private function fetchExchangeRateFromDatabase(string $fromCurrency, string $toCurrency): ?float
    {
        $currencyRateRepo = $this->entityManager->getRepository('App\Entity\CurrencyRate');
        
        // Try direct conversion (from -> to)
        $directRate = $currencyRateRepo->findOneBy([
            'baseCurrency' => $fromCurrency,
            'targetCurrency' => $toCurrency
        ]);

        if ($directRate) {
            $rate = (float) $directRate->getRate();
            $this->logger->debug('Direct exchange rate found', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'rate' => $rate
            ]);
            return $rate;
        }

        // Try reverse conversion (to -> from) and invert
        $reverseRate = $currencyRateRepo->findOneBy([
            'baseCurrency' => $toCurrency,
            'targetCurrency' => $fromCurrency
        ]);

        if ($reverseRate) {
            $rate = 1.0 / (float) $reverseRate->getRate();
            $this->logger->debug('Reverse exchange rate found and inverted', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'original_rate' => $reverseRate->getRate(),
                'inverted_rate' => $rate
            ]);
            return $rate;
        }

        $this->logger->warning('No exchange rate found in database', [
            'from' => $fromCurrency,
            'to' => $toCurrency
        ]);

        return null;
    }

    /**
     * Generate cache key for currency pair.
     * 
     * @param string $fromCurrency Source currency
     * @param string $toCurrency Target currency
     * 
     * @return string Cache key
     */
    private function generateCacheKey(string $fromCurrency, string $toCurrency): string
    {
        return self::CACHE_KEY_PREFIX . ':' . strtoupper($fromCurrency) . '_' . strtoupper($toCurrency);
    }

    /**
     * Clear cached exchange rates for specific currency pair or all rates.
     * 
     * @param string|null $fromCurrency Source currency (null = clear all)
     * @param string|null $toCurrency Target currency (null = clear all)
     * 
     * @return bool True if cache cleared successfully
     */
    public function clearRateCache(?string $fromCurrency = null, ?string $toCurrency = null): bool
    {
        if ($fromCurrency && $toCurrency) {
            // Clear specific currency pair
            $cacheKey = $this->generateCacheKey($fromCurrency, $toCurrency);
            $result = $this->cacheService->delete($cacheKey);
            
            $this->logger->info('Currency rate cache cleared for pair', [
                'cache_key' => $cacheKey,
                'success' => $result
            ]);
            
            return $result;
        }

        // Clear all currency rates
        $pattern = self::CACHE_KEY_PREFIX . ':*';
        $result = $this->cacheService->clear($pattern);
        
        $this->logger->info('All currency rate cache cleared', [
            'pattern' => $pattern,
            'success' => $result
        ]);
        
        return $result;
    }
}