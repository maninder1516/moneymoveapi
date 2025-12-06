<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CurrencyRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for CurrencyRate entity operations.
 * 
 * @extends ServiceEntityRepository<CurrencyRate>
 */
class CurrencyRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CurrencyRate::class);
    }

    /**
     * Find exchange rate between two currencies
     */
    public function findExchangeRate(string $baseCurrency, string $targetCurrency): ?CurrencyRate
    {
        return $this->findOneBy([
            'baseCurrency' => $baseCurrency,
            'targetCurrency' => $targetCurrency
        ]);
    }

    /**
     * Get all available currency pairs
     */
    public function getAllCurrencyPairs(): array
    {
        return $this->findAll();
    }

    /**
     * Find rates for a specific base currency
     */
    public function findRatesForBaseCurrency(string $baseCurrency): array
    {
        return $this->findBy(['baseCurrency' => $baseCurrency]);
    }
}