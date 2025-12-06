<?php

declare(strict_types=1);

namespace App\Service\Api\V1;

use App\Entity\Transaction;
use App\Entity\LedgerEntry;
use App\Enum\LedgerEntryType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing double-entry bookkeeping ledger entries.
 * 
 * Creates proper accounting entries for all financial transactions
 * following double-entry bookkeeping principles where every debit
 * has a corresponding credit entry.
 * 
 * @author MoneyMove API Team
 * @version 1.0.0
 * @since 2025-12-06
 */
class LedgerService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Create ledger entries for a transfer transaction.
     * 
     * Creates both debit and credit entries to maintain
     * double-entry bookkeeping principles.
     * 
     * @param Transaction $transaction The completed transaction
     * @param array $conversionResult Currency conversion details
     */
    public function createTransferLedgerEntries(Transaction $transaction, array $conversionResult): void
    {
        // Create debit entry for source account
        $this->createLedgerEntry(
            $transaction,
            $transaction->getFromAccount(),
            LedgerEntryType::DEBIT,
            $transaction->getAmount(),
            $transaction->getCurrency(),
            'Transfer debit - ' . $transaction->getReferenceId()
        );

        // Create credit entry for destination account
        $creditAmount = $conversionResult['converted_amount'];
        $creditCurrency = $transaction->getConvertedCurrency() ?? $transaction->getCurrency();
        
        $this->createLedgerEntry(
            $transaction,
            $transaction->getToAccount(),
            LedgerEntryType::CREDIT,
            $creditAmount,
            $creditCurrency,
            'Transfer credit - ' . $transaction->getReferenceId()
        );
    }

    /**
     * Create a single ledger entry.
     */
    private function createLedgerEntry(
        Transaction $transaction,
        $account,
        LedgerEntryType $type,
        string $amount,
        string $currency,
        string $description
    ): LedgerEntry {
        $ledgerEntry = new LedgerEntry();
        $ledgerEntry->setTransaction($transaction);
        $ledgerEntry->setAccount($account);
        $ledgerEntry->setEntryType($type);
        $ledgerEntry->setAmount($amount);
        $ledgerEntry->setCurrency($currency);
        $ledgerEntry->setNote($description);

        $this->entityManager->persist($ledgerEntry);
        $this->entityManager->flush();

        return $ledgerEntry;
    }
}