<?php

declare(strict_types=1);

namespace App\Service\Api\V1;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for handling currency conversion operations.
 * 
 * Provides currency conversion functionality using exchange rates
 * stored in the database. Supports both direct and inverse conversions.
 * 
 * @author MoneyMove API Team
 * @version 1.0.0
 * @since 2025-12-06
 */
class CurrencyService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Convert amount from one currency to another.
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

        $currencyRateRepo = $this->entityManager->getRepository('App\Entity\CurrencyRate');
        
        // Try direct conversion (from -> to)
        $directRate = $currencyRateRepo->findOneBy([
            'baseCurrency' => $fromCurrency,
            'targetCurrency' => $toCurrency
        ]);

        if ($directRate) {
            $rate = (float) $directRate->getRate();
            $convertedAmount = number_format((float) $amount * $rate, 2, '.', '');
            return [
                'converted_amount' => $convertedAmount,
                'exchange_rate' => (string) $rate
            ];
        }

        // Try reverse conversion (to -> from) and invert
        $reverseRate = $currencyRateRepo->findOneBy([
            'baseCurrency' => $toCurrency,
            'targetCurrency' => $fromCurrency
        ]);

        if ($reverseRate) {
            $rate = 1.0 / (float) $reverseRate->getRate();
            $convertedAmount = number_format((float) $amount * $rate, 2, '.', '');
            return [
                'converted_amount' => $convertedAmount,
                'exchange_rate' => (string) $rate
            ];
        }

        throw new \RuntimeException("No exchange rate found for {$fromCurrency} to {$toCurrency}");
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
        $result = $this->convertAmount('1.0', $fromCurrency, $toCurrency);
        return $result['exchange_rate'];
    }
}