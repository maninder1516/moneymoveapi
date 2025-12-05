<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\TransactionRepository')]
#[ORM\Table(name: 'transactions')]
#[ORM\Index(columns: ['reference_id'], name: 'idx_reference_id')]
#[ORM\Index(columns: ['from_account'], name: 'idx_from_account')]
#[ORM\Index(columns: ['to_account'], name: 'idx_to_account')]
#[ORM\Index(columns: ['type'], name: 'idx_type')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
class Transaction
{
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_WITHDRAWAL = 'withdrawal';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: false)]
    private string $referenceId;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'outgoingTransactions')]
    #[ORM\JoinColumn(name: 'from_account', referencedColumnName: 'id', nullable: true)]
    private ?Account $fromAccount = null;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'incomingTransactions')]
    #[ORM\JoinColumn(name: 'to_account', referencedColumnName: 'id', nullable: true)]
    private ?Account $toAccount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: false)]
    private string $amount;

    #[ORM\Column(type: Types::STRING, length: 3, nullable: false)]
    private string $currency;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: true)]
    private ?string $convertedAmount = null;

    #[ORM\Column(type: Types::STRING, length: 3, nullable: true)]
    private ?string $convertedCurrency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8, nullable: true)]
    private ?string $rateUsed = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TransactionType::class)]
    private TransactionType $type;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: TransactionStatus::class)]
    private TransactionStatus $status = TransactionStatus::PENDING;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $completedAt = null;

    #[ORM\OneToMany(mappedBy: 'transaction', targetEntity: LedgerEntry::class, cascade: ['persist', 'remove'])]
    private Collection $ledgerEntries;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->ledgerEntries = new ArrayCollection();
        $this->generateReferenceId();
    }

    private function generateReferenceId(): void
    {
        $this->referenceId = \Ramsey\Uuid\Uuid::uuid4()->toString();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReferenceId(): string
    {
        return $this->referenceId;
    }

    public function setReferenceId(string $referenceId): static
    {
        $this->referenceId = $referenceId;
        return $this;
    }

    public function getFromAccount(): ?Account
    {
        return $this->fromAccount;
    }

    public function setFromAccount(?Account $fromAccount): static
    {
        $this->fromAccount = $fromAccount;
        return $this;
    }

    public function getToAccount(): ?Account
    {
        return $this->toAccount;
    }

    public function setToAccount(?Account $toAccount): static
    {
        $this->toAccount = $toAccount;
        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = strtoupper($currency);
        return $this;
    }

    public function getConvertedAmount(): ?string
    {
        return $this->convertedAmount;
    }

    public function setConvertedAmount(?string $convertedAmount): static
    {
        $this->convertedAmount = $convertedAmount;
        return $this;
    }

    public function getConvertedCurrency(): ?string
    {
        return $this->convertedCurrency;
    }

    public function setConvertedCurrency(?string $convertedCurrency): static
    {
        $this->convertedCurrency = $convertedCurrency ? strtoupper($convertedCurrency) : null;
        return $this;
    }

    public function getRateUsed(): ?string
    {
        return $this->rateUsed;
    }

    public function setRateUsed(?string $rateUsed): static
    {
        $this->rateUsed = $rateUsed;
        return $this;
    }

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function setType(TransactionType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function setStatus(TransactionStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTime
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTime $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    /**
     * @return Collection<int, LedgerEntry>
     */
    public function getLedgerEntries(): Collection
    {
        return $this->ledgerEntries;
    }

    // Helper methods

    public function isPending(): bool
    {
        return $this->status === TransactionStatus::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === TransactionStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === TransactionStatus::FAILED;
    }

    public function complete(): static
    {
        $this->status = TransactionStatus::COMPLETED;
        $this->completedAt = new \DateTime();
        return $this;
    }

    public function fail(?string $reason = null): static
    {
        $this->status = TransactionStatus::FAILED;
        if ($reason) {
            $this->reason = $reason;
        }
        $this->completedAt = new \DateTime();
        return $this;
    }

    public function isTransfer(): bool
    {
        return $this->type === TransactionType::TRANSFER;
    }

    public function isDeposit(): bool
    {
        return $this->type === TransactionType::DEPOSIT;
    }

    public function isWithdrawal(): bool
    {
        return $this->type === TransactionType::WITHDRAWAL;
    }

    public function hasCurrencyConversion(): bool
    {
        return $this->convertedAmount !== null && $this->convertedCurrency !== null;
    }

    public function getEffectiveAmount(): string
    {
        return $this->convertedAmount ?? $this->amount;
    }

    public function getEffectiveCurrency(): string
    {
        return $this->convertedCurrency ?? $this->currency;
    }
}