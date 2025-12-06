<?php

declare(strict_types=1);

namespace App\Service\Api\V1;

use App\Entity\Account;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Service\AccountEncryptionService;
use App\Exception\Api\AccountNotFoundException;
use App\Exception\Api\InsufficientBalanceException;
use Doctrine\ORM\EntityManagerInterface;

class AccountService
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly AccountEncryptionService $encryptionService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Create a new account for a user
     */
    public function createAccount(User $user, string $currency = 'INR'): Account
    {
        // Validate currency
        $this->validateCurrency($currency);
        
        // Generate unique account number
        $accountNumber = $this->generateUniqueAccountNumber($user);
        
        // Encrypt account number
        $encryptionData = $this->encryptionService->encryptAccountNumber($accountNumber);
        
        // Create account
        $account = $this->accountRepository->createAccount(
            $user,
            $encryptionData['encrypted'],
            $encryptionData['hash'],
            $currency
        );

        return $account;
    }

    /**
     * Get all accounts for a user
     */
    public function getUserAccounts(User $user, int $page = 1, int $limit = 10): array
    {
        return $this->accountRepository->findByUserWithPagination($user, $page, $limit);
    }

    /**
     * Get account count for a user
     */
    public function getUserAccountCount(User $user): int
    {
        return $this->accountRepository->countByUser($user);
    }

    /**
     * Find account by ID and ensure it belongs to the user
     */
    public function getUserAccount(User $user, int $accountId): Account
    {
        $account = $this->accountRepository->find($accountId);
        
        if (!$account) {
            throw new AccountNotFoundException("Account with ID {$accountId} not found");
        }

        if ($account->getUser()->getId() !== $user->getId()) {
            throw new AccountNotFoundException("Account not found or access denied");
        }

        return $account;
    }

    /**
     * Get account by account number hash
     */
    public function getAccountByNumber(string $accountNumber): Account
    {
        $hash = $this->encryptionService->hashAccountNumber($accountNumber);
        $account = $this->accountRepository->findByAccountNumberHash($hash);
        
        if (!$account) {
            throw new AccountNotFoundException("Account not found");
        }

        return $account;
    }

    /**
     * Get decrypted account number
     */
    public function getDecryptedAccountNumber(Account $account): string
    {
        return $this->encryptionService->decryptAccountNumber($account->getAccountNumberEnc());
    }

    /**
     * Update account balance (internal use)
     */
    public function updateBalance(Account $account, string $newBalance): void
    {
        if (bccomp($newBalance, '0', 2) < 0) {
            throw new \InvalidArgumentException('Balance cannot be negative');
        }

        $account->setBalance($newBalance);
        $this->entityManager->flush();
    }

    /**
     * Add money to account
     */
    public function addBalance(Account $account, string $amount): Account
    {
        if ((float) $amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $currentBalance = (float) $account->getBalance();
        $addAmount = (float) $amount;
        $newBalance = number_format($currentBalance + $addAmount, 2, '.', '');
        
        $account->setBalance($newBalance);
        $this->entityManager->flush();

        return $account;
    }

    /**
     * Subtract money from account
     */
    public function subtractBalance(Account $account, string $amount): Account
    {
        if ((float) $amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if (!$account->hasBalance($amount)) {
            throw new InsufficientBalanceException('Insufficient account balance');
        }

        $currentBalance = (float) $account->getBalance();
        $subtractAmount = (float) $amount;
        $newBalance = number_format($currentBalance - $subtractAmount, 2, '.', '');
        
        $account->setBalance($newBalance);
        $this->entityManager->flush();

        return $account;
    }

    /**
     * Transfer money between accounts
     */
    public function transferMoney(Account $fromAccount, Account $toAccount, string $amount): array
    {
        if ((float) $amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be positive');
        }

        if (!$fromAccount->hasBalance($amount)) {
            throw new InsufficientBalanceException('Insufficient balance in source account');
        }

        // Start transaction
        $this->entityManager->beginTransaction();

        try {
            // Subtract from source account
            $this->subtractBalance($fromAccount, $amount);
            
            // Add to destination account (handle currency conversion if needed)
            $finalAmount = $amount;
            $convertedAmount = null;
            $exchangeRate = null;
            
            if ($fromAccount->getCurrency() !== $toAccount->getCurrency()) {
                // Implement currency conversion using exchange rates
                $conversionResult = $this->convertCurrency(
                    $amount, 
                    $fromAccount->getCurrency(), 
                    $toAccount->getCurrency()
                );
                $finalAmount = $conversionResult['amount'];
                $convertedAmount = $finalAmount;
                $exchangeRate = $conversionResult['rate'];
            }
            
            $this->addBalance($toAccount, $finalAmount);

            $this->entityManager->commit();

            return [
                'from_account' => $fromAccount,
                'to_account' => $toAccount,
                'amount' => $amount,
                'converted_amount' => $convertedAmount,
                'exchange_rate' => $exchangeRate,
                'currency' => $fromAccount->getCurrency(),
                'target_currency' => $toAccount->getCurrency()
            ];
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Convert currency using exchange rates
     */
    private function convertCurrency(string $amount, string $fromCurrency, string $toCurrency): array
    {
        if ($fromCurrency === $toCurrency) {
            return ['amount' => $amount, 'rate' => '1.0'];
        }

        // Get currency rate repository
        $currencyRateRepo = $this->entityManager->getRepository('App\Entity\CurrencyRate');
        
        // Try direct conversion (from -> to)
        $directRate = $currencyRateRepo->findOneBy([
            'baseCurrency' => $fromCurrency,
            'targetCurrency' => $toCurrency
        ]);

        if ($directRate) {
            $rate = (float) $directRate->getRate();
            $convertedAmount = number_format((float) $amount * $rate, 2, '.', '');
            return ['amount' => $convertedAmount, 'rate' => (string) $rate];
        }

        // Try reverse conversion (to -> from) and invert
        $reverseRate = $currencyRateRepo->findOneBy([
            'baseCurrency' => $toCurrency,
            'targetCurrency' => $fromCurrency
        ]);

        if ($reverseRate) {
            $rate = 1.0 / (float) $reverseRate->getRate();
            $convertedAmount = number_format((float) $amount * $rate, 2, '.', '');
            return ['amount' => $convertedAmount, 'rate' => (string) $rate];
        }

        // If no direct or reverse rate found, throw exception
        throw new \RuntimeException("No exchange rate found for {$fromCurrency} to {$toCurrency}");
    }

    /**
     * Delete account (only if balance is zero)
     */
    public function deleteAccount(User $user, int $accountId): bool
    {
        $account = $this->getUserAccount($user, $accountId);
        
        return $this->accountRepository->safeDeleteAccount($account);
    }

    /**
     * Get user's total balance across all accounts in specific currency
     */
    public function getUserTotalBalance(User $user, string $currency = 'INR'): string
    {
        return $this->accountRepository->getTotalBalanceByUser($user, $currency);
    }

    /**
     * Search user's accounts
     */
    public function searchUserAccounts(User $user, string $searchTerm): array
    {
        // For now, just return user's accounts
        // TODO: Implement search within user's accounts
        return $this->accountRepository->findByUser($user);
    }

    private function generateUniqueAccountNumber(User $user): string
    {
        $maxAttempts = 10;
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            $accountNumber = $this->encryptionService->generateAccountNumber($user->getId());
            $hash = $this->encryptionService->hashAccountNumber($accountNumber);
            
            if (!$this->accountRepository->existsByAccountNumberHash($hash)) {
                return $accountNumber;
            }
        }

        throw new \RuntimeException('Failed to generate unique account number after multiple attempts');
    }

    private function validateCurrency(string $currency): void
    {
        $supportedCurrencies = ['INR', 'USD', 'EUR', 'GBP'];
        
        if (!in_array(strtoupper($currency), $supportedCurrencies)) {
            throw new \InvalidArgumentException("Unsupported currency: {$currency}");
        }
    }
}