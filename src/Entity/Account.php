<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\AccountRepository')]
#[ORM\Table(name: 'accounts')]
#[ORM\Index(columns: ['user_id'], name: 'idx_account_user')]
#[ORM\UniqueConstraint(name: 'UNIQ_ACCOUNT_NUMBER_HASH', columns: ['account_number_hash'])]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'accounts')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    private string $accountNumberEnc;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true, nullable: false)]
    private string $accountNumberHash;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: false)]
    private string $balance = '0.00';

    #[ORM\Column(type: Types::STRING, length: 3, nullable: false)]
    private string $currency = 'INR';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTime $updatedAt;

    #[ORM\OneToMany(mappedBy: 'fromAccount', targetEntity: Transaction::class)]
    private Collection $outgoingTransactions;

    #[ORM\OneToMany(mappedBy: 'toAccount', targetEntity: Transaction::class)]
    private Collection $incomingTransactions;

    #[ORM\OneToMany(mappedBy: 'account', targetEntity: LedgerEntry::class)]
    private Collection $ledgerEntries;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
        $this->outgoingTransactions = new ArrayCollection();
        $this->incomingTransactions = new ArrayCollection();
        $this->ledgerEntries = new ArrayCollection();
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getAccountNumberEnc(): string
    {
        return $this->accountNumberEnc;
    }

    public function setAccountNumberEnc(string $accountNumberEnc): static
    {
        $this->accountNumberEnc = $accountNumberEnc;
        return $this;
    }

    public function getAccountNumberHash(): string
    {
        return $this->accountNumberHash;
    }

    public function setAccountNumberHash(string $accountNumberHash): static
    {
        $this->accountNumberHash = $accountNumberHash;
        return $this;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): static
    {
        $this->balance = $balance;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getOutgoingTransactions(): Collection
    {
        return $this->outgoingTransactions;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getIncomingTransactions(): Collection
    {
        return $this->incomingTransactions;
    }

    /**
     * @return Collection<int, LedgerEntry>
     */
    public function getLedgerEntries(): Collection
    {
        return $this->ledgerEntries;
    }

    // Helper methods

    public function addBalance(string $amount): static
    {
        $this->balance = bcadd($this->balance, $amount, 2);
        $this->updateTimestamp();
        return $this;
    }

    public function subtractBalance(string $amount): static
    {
        $currentBalance = $this->balance;
        $newBalance = bcsub($currentBalance, $amount, 2);
        
        if (bccomp($newBalance, '0', 2) < 0) {
            throw new \InvalidArgumentException('Insufficient balance');
        }
        
        $this->balance = $newBalance;
        $this->updateTimestamp();
        return $this;
    }

    public function hasBalance(string $amount): bool
    {
        // Convert to float for comparison (in production, consider using bcmath extension)
        return (float) $this->balance >= (float) $amount;
    }
}