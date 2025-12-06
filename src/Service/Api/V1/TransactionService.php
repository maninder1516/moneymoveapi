<?php

declare(strict_types=1);

namespace App\Service\Api\V1;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Entity\LedgerEntry;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Enum\LedgerEntryType;
use App\Exception\Api\InsufficientBalanceException;
use App\Service\AccountEncryptionService;
use App\Service\LanguageService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service responsible for managing financial transactions.
 * 
 * Handles the complete transaction lifecycle from initiation to settlement,
 * including proper double-entry bookkeeping with ledger entries.
 * Follows financial industry best practices for money movement operations.
 * 
 * @author MoneyMove API Team
 * @version 1.0.0
 * @since 2025-12-06
 */
class TransactionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CurrencyService $currencyService,
        private readonly LedgerService $ledgerService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create and process a money transfer transaction.
     * 
     * This is the main entry point for transfer operations. It handles:
     * 1. Transaction creation and validation
     * 2. Currency conversion if needed
     * 3. Account balance verification
     * 4. Transaction processing and settlement
     * 5. Ledger entry creation for audit trail
     * 
     * @param Account $fromAccount Source account for the transfer
     * @param Account $toAccount Destination account for the transfer
     * @param string $amount Amount to transfer in source currency
     * @param string|null $note Optional transfer description
     * 
     * @return Transaction The completed transaction with all details
     * 
     * @throws InsufficientBalanceException If source account lacks sufficient funds
     * @throws \RuntimeException If currency conversion fails or transaction processing fails
     */
    public function processTransfer(
        Account $fromAccount, 
        Account $toAccount, 
        string $amount, 
        ?string $note = null
    ): Transaction {
        $transactionId = $this->generateTransactionId();
        
        $this->logger->info('Transaction processing started', [
            'transaction_id' => $transactionId,
            'from_account' => $fromAccount->getId(),
            'to_account' => $toAccount->getId(),
            'amount' => $amount,
            'from_currency' => $fromAccount->getCurrency(),
            'to_currency' => $toAccount->getCurrency()
        ]);

        $this->entityManager->beginTransaction();

        try {
            // Step 1: Create transaction record
            $transaction = $this->createTransaction(
                $fromAccount,
                $toAccount,
                $amount,
                $note,
                $transactionId
            );

            // Step 2: Validate and process currency conversion if needed
            $conversionResult = $this->processCurrencyConversion(
                $transaction,
                $fromAccount->getCurrency(),
                $toAccount->getCurrency(),
                $amount
            );

            // Step 3: Validate sufficient balance
            $this->validateSufficientBalance($fromAccount, $amount);

            // Step 4: Execute account settlements
            $this->settleAccounts($transaction, $fromAccount, $toAccount, $amount, $conversionResult);

            // Step 5: Create ledger entries for audit trail
            $this->ledgerService->createTransferLedgerEntries($transaction, $conversionResult);

            // Step 6: Mark transaction as completed
            $this->completeTransaction($transaction);

            $this->entityManager->commit();

            $this->logger->info('Transaction completed successfully', [
                'transaction_id' => $transactionId,
                'status' => 'completed',
                'amount_debited' => $amount,
                'amount_credited' => $conversionResult['converted_amount'],
                'exchange_rate' => $conversionResult['exchange_rate']
            ]);

            return $transaction;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            // Mark transaction as failed if it exists
            if (isset($transaction)) {
                $this->failTransaction($transaction, $e->getMessage());
            }

            $this->logger->error('Transaction processing failed', [
                'transaction_id' => $transactionId ?? 'unknown',
                'error' => $e->getMessage(),
                'error_type' => get_class($e)
            ]);

            throw $e;
        }
    }

    /**
     * Create a new transaction record in pending status.
     */
    private function createTransaction(
        Account $fromAccount,
        Account $toAccount,
        string $amount,
        ?string $note,
        string $transactionId
    ): Transaction {
        $transaction = new Transaction();
        $transaction->setReferenceId($transactionId);
        $transaction->setFromAccount($fromAccount);
        $transaction->setToAccount($toAccount);
        $transaction->setAmount($amount);
        $transaction->setCurrency($fromAccount->getCurrency());
        $transaction->setType(TransactionType::TRANSFER);
        $transaction->setStatus(TransactionStatus::PENDING);
        $transaction->setReason($note);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }

    /**
     * Process currency conversion if needed.
     */
    private function processCurrencyConversion(
        Transaction $transaction,
        string $fromCurrency,
        string $toCurrency,
        string $amount
    ): array {
        if ($fromCurrency === $toCurrency) {
            return [
                'converted_amount' => $amount,
                'exchange_rate' => '1.0',
                'requires_conversion' => false
            ];
        }

        $conversionResult = $this->currencyService->convertAmount($amount, $fromCurrency, $toCurrency);
        
        // Update transaction with conversion details
        $transaction->setConvertedAmount($conversionResult['converted_amount']);
        $transaction->setConvertedCurrency($toCurrency);
        $transaction->setRateUsed($conversionResult['exchange_rate']);

        $this->entityManager->flush();

        return [
            'converted_amount' => $conversionResult['converted_amount'],
            'exchange_rate' => $conversionResult['exchange_rate'],
            'requires_conversion' => true
        ];
    }

    /**
     * Validate that source account has sufficient balance.
     */
    private function validateSufficientBalance(Account $account, string $amount): void
    {
        if (!$account->hasBalance($amount)) {
            throw new InsufficientBalanceException(
                'Insufficient balance in source account. ' .
                "Required: {$amount}, Available: {$account->getBalance()}"
            );
        }
    }

    /**
     * Execute the actual account balance settlements.
     */
    private function settleAccounts(
        Transaction $transaction,
        Account $fromAccount,
        Account $toAccount,
        string $debitAmount,
        array $conversionResult
    ): void {
        // Debit source account
        $currentFromBalance = (float) $fromAccount->getBalance();
        $newFromBalance = number_format($currentFromBalance - (float) $debitAmount, 2, '.', '');
        $fromAccount->setBalance($newFromBalance);

        // Credit destination account
        $currentToBalance = (float) $toAccount->getBalance();
        $creditAmount = $conversionResult['converted_amount'];
        $newToBalance = number_format($currentToBalance + (float) $creditAmount, 2, '.', '');
        $toAccount->setBalance($newToBalance);

        $this->entityManager->flush();

        $this->logger->info('Account settlement completed', [
            'transaction_id' => $transaction->getReferenceId(),
            'from_account' => $fromAccount->getId(),
            'to_account' => $toAccount->getId(),
            'amount_debited' => $debitAmount,
            'amount_credited' => $creditAmount,
            'new_from_balance' => $newFromBalance,
            'new_to_balance' => $newToBalance
        ]);
    }

    /**
     * Mark transaction as completed.
     */
    private function completeTransaction(Transaction $transaction): void
    {
        $transaction->setStatus(TransactionStatus::COMPLETED);
        $transaction->setCompletedAt(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * Mark transaction as failed with error reason.
     */
    private function failTransaction(Transaction $transaction, string $reason): void
    {
        try {
            $transaction->setStatus(TransactionStatus::FAILED);
            $transaction->setReason($reason);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Log but don't throw to avoid masking original error
            $this->logger->error('Failed to mark transaction as failed', [
                'transaction_id' => $transaction->getReferenceId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate unique transaction ID.
     */
    private function generateTransactionId(): string
    {
        return 'TXN-' . date('YmdHis') . '-' . strtoupper(substr(uniqid(), -6));
    }
}