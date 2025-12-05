<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\CurrencyRateRepository')]
#[ORM\Table(name: 'currency_rates')]
#[ORM\UniqueConstraint(name: 'unique_rate', columns: ['base_currency', 'target_currency'])]
#[ORM\Index(columns: ['base_currency'], name: 'idx_base_currency')]
#[ORM\Index(columns: ['target_currency'], name: 'idx_target_currency')]
class CurrencyRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 3, nullable: false)]
    private string $baseCurrency;

    #[ORM\Column(type: Types::STRING, length: 3, nullable: false)]
    private string $targetCurrency;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8, nullable: false)]
    private string $rate;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBaseCurrency(): string
    {
        return $this->baseCurrency;
    }

    public function setBaseCurrency(string $baseCurrency): static
    {
        $this->baseCurrency = strtoupper($baseCurrency);
        return $this;
    }

    public function getTargetCurrency(): string
    {
        return $this->targetCurrency;
    }

    public function setTargetCurrency(string $targetCurrency): static
    {
        $this->targetCurrency = strtoupper($targetCurrency);
        return $this;
    }

    public function getRate(): string
    {
        return $this->rate;
    }

    public function setRate(string $rate): static
    {
        $this->rate = $rate;
        $this->updateTimestamp();
        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    // Helper methods

    public function getCurrencyPair(): string
    {
        return $this->baseCurrency . '/' . $this->targetCurrency;
    }

    public function getInverseRate(): string
    {
        return bcdiv('1', $this->rate, 8);
    }

    public function convert(string $amount): string
    {
        return bcmul($amount, $this->rate, 2);
    }

    public function isExpired(int $maxAgeMinutes = 60): bool
    {
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $this->updatedAt->getTimestamp();
        return $diff > ($maxAgeMinutes * 60);
    }
}