<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\LedgerEntryType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\LedgerEntryRepository')]
#[ORM\Table(name: 'ledger_entries')]
#[ORM\Index(columns: ['transaction_id'], name: 'idx_ledger_transaction')]
#[ORM\Index(columns: ['account_id'], name: 'idx_ledger_account')]
#[ORM\Index(columns: ['entry_type'], name: 'idx_entry_type')]
#[ORM\Index(columns: ['created_at'], name: 'idx_ledger_created_at')]
class LedgerEntry
{
    public const ENTRY_TYPE_DEBIT = 'debit';
    public const ENTRY_TYPE_CREDIT = 'credit';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Transaction::class, inversedBy: 'ledgerEntries')]
    #[ORM\JoinColumn(name: 'transaction_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Transaction $transaction;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'ledgerEntries')]
    #[ORM\JoinColumn(name: 'account_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: LedgerEntryType::class)]
    private LedgerEntryType $entryType;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2, nullable: false)]
    private string $amount;

    #[ORM\Column(type: Types::STRING, length: 3, nullable: false)]
    private string $currency;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(Transaction $transaction): static
    {
        $this->transaction = $transaction;
        return $this;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function getEntryType(): LedgerEntryType
    {
        return $this->entryType;
    }

    public function setEntryType(LedgerEntryType $entryType): static
    {
        $this->entryType = $entryType;
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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Helper methods

    public function isDebit(): bool
    {
        return $this->entryType === LedgerEntryType::DEBIT;
    }

    public function isCredit(): bool
    {
        return $this->entryType === LedgerEntryType::CREDIT;
    }

    public function getSignedAmount(): string
    {
        return $this->isDebit() ? '-' . $this->amount : $this->amount;
    }

    public static function createDebit(Account $account, Transaction $transaction, string $amount, string $currency, ?string $note = null): self
    {
        $entry = new self();
        $entry->setAccount($account)
              ->setTransaction($transaction)
              ->setEntryType(LedgerEntryType::DEBIT)
              ->setAmount($amount)
              ->setCurrency($currency)
              ->setNote($note);

        return $entry;
    }

    public static function createCredit(Account $account, Transaction $transaction, string $amount, string $currency, ?string $note = null): self
    {
        $entry = new self();
        $entry->setAccount($account)
              ->setTransaction($transaction)
              ->setEntryType(LedgerEntryType::CREDIT)
              ->setAmount($amount)
              ->setCurrency($currency)
              ->setNote($note);

        return $entry;
    }
}