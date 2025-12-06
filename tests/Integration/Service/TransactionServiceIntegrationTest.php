<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\Account;
use App\Entity\User;
use App\Entity\CurrencyRate;
use App\Service\Api\V1\TransactionService;
use App\Service\Api\V1\CurrencyService;
use App\Exception\Api\InsufficientBalanceException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Integration tests for TransactionService.
 * 
 * Tests the complete transaction flow including:
 * - Database transactions and rollbacks
 * - Real currency conversion with database rates
 * - Account balance updates
 * - Ledger entry creation
 * - Error handling with database state
 * 
 * @covers TransactionService
 * @group integration
 */
class TransactionServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private TransactionService $transactionService;
    private CurrencyService $currencyService;

    protected function setUp(): void
    {
        self::bootKernel();
        
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->transactionService = static::getContainer()->get(TransactionService::class);
        $this->currencyService = static::getContainer()->get(CurrencyService::class);

        // Start with clean database
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback any changes made during tests
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    public function testProcessTransferWithSameCurrency(): void
    {
        // Arrange
        $user1 = $this->createTestUser('user1@example.com');
        $user2 = $this->createTestUser('user2@example.com');
        $fromAccount = $this->createTestAccount($user1, 'USD', '1000.00');
        $toAccount = $this->createTestAccount($user2, 'USD', '500.00');

        $this->entityManager->flush();

        // Act
        $transaction = $this->transactionService->processTransfer($fromAccount, $toAccount, '100.00');

        // Assert
        $this->assertEquals('100.00', $transaction->getAmount());
        $this->assertEquals('USD', $transaction->getCurrency());
        $this->assertNull($transaction->getConvertedAmount());
        $this->assertEquals('900.00', $fromAccount->getBalance());
        $this->assertEquals('600.00', $toAccount->getBalance());
        
        // Verify ledger entries were created
        $this->entityManager->refresh($transaction);
        $ledgerEntries = $transaction->getLedgerEntries();
        $this->assertCount(2, $ledgerEntries);
    }

    public function testProcessTransferWithCurrencyConversion(): void
    {
        // Arrange
        $this->createTestCurrencyRate('USD', 'EUR', '0.92');
        
        $user1 = $this->createTestUser('user1@example.com');
        $user2 = $this->createTestUser('user2@example.com');
        $fromAccount = $this->createTestAccount($user1, 'USD', '1000.00');
        $toAccount = $this->createTestAccount($user2, 'EUR', '500.00');

        $this->entityManager->flush();

        // Act
        $transaction = $this->transactionService->processTransfer($fromAccount, $toAccount, '100.00');

        // Assert
        $this->assertEquals('100.00', $transaction->getAmount());
        $this->assertEquals('USD', $transaction->getCurrency());
        $this->assertEquals('92.00', $transaction->getConvertedAmount());
        $this->assertEquals('EUR', $transaction->getConvertedCurrency());
        $this->assertEquals('0.92', $transaction->getRateUsed());
        
        // Check account balances
        $this->assertEquals('900.00', $fromAccount->getBalance());
        $this->assertEquals('592.00', $toAccount->getBalance()); // 500 + 92
        
        // Verify ledger entries were created
        $this->entityManager->refresh($transaction);
        $ledgerEntries = $transaction->getLedgerEntries();
        $this->assertCount(2, $ledgerEntries);
    }

    public function testProcessTransferWithInsufficientBalance(): void
    {
        // Arrange
        $user1 = $this->createTestUser('user1@example.com');
        $user2 = $this->createTestUser('user2@example.com');
        $fromAccount = $this->createTestAccount($user1, 'USD', '50.00'); // Insufficient balance
        $toAccount = $this->createTestAccount($user2, 'USD', '500.00');

        $this->entityManager->flush();

        // Act & Assert
        $this->expectException(InsufficientBalanceException::class);
        $this->transactionService->processTransfer($fromAccount, $toAccount, '100.00');

        // Verify balances remained unchanged
        $this->entityManager->refresh($fromAccount);
        $this->entityManager->refresh($toAccount);
        $this->assertEquals('50.00', $fromAccount->getBalance());
        $this->assertEquals('500.00', $toAccount->getBalance());
    }

    public function testProcessTransferWithInvalidCurrencyConversion(): void
    {
        // Arrange - No currency rate exists for XYZ
        $user1 = $this->createTestUser('user1@example.com');
        $user2 = $this->createTestUser('user2@example.com');
        $fromAccount = $this->createTestAccount($user1, 'USD', '1000.00');
        $toAccount = $this->createTestAccount($user2, 'XYZ', '500.00'); // Invalid currency

        $this->entityManager->flush();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No exchange rate found for USD to XYZ');
        
        $this->transactionService->processTransfer($fromAccount, $toAccount, '100.00');

        // Verify balances remained unchanged
        $this->entityManager->refresh($fromAccount);
        $this->entityManager->refresh($toAccount);
        $this->assertEquals('1000.00', $fromAccount->getBalance());
        $this->assertEquals('500.00', $toAccount->getBalance());
    }

    public function testCurrencyConversionCaching(): void
    {
        // Arrange
        $this->createTestCurrencyRate('USD', 'EUR', '0.92');
        $this->entityManager->flush();

        // First conversion (should hit database)
        $start = microtime(true);
        $result1 = $this->currencyService->convertAmount('100.00', 'USD', 'EUR');
        $time1 = microtime(true) - $start;

        // Second conversion (should hit cache)
        $start = microtime(true);
        $result2 = $this->currencyService->convertAmount('100.00', 'USD', 'EUR');
        $time2 = microtime(true) - $start;

        // Assert
        $this->assertEquals('92.00', $result1['converted_amount']);
        $this->assertEquals('0.92', $result1['exchange_rate']);
        $this->assertEquals($result1, $result2);
        
        // Second call should be significantly faster due to caching
        $this->assertLessThan($time1, $time2);
    }

    private function createTestUser(string $email): User
    {
        $user = new User();
        $user->setName('Test User');
        $user->setEmail($email);
        $user->setUsername('test_user_' . uniqid());
        
        // Set a hashed password
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, 'test123'));

        $this->entityManager->persist($user);
        return $user;
    }

    private function createTestAccount(User $user, string $currency, string $balance): Account
    {
        $account = new Account();
        $account->setUser($user);
        
        // Use the AccountEncryptionService to properly create account number
        $accountEncryptionService = static::getContainer()->get(\App\Service\AccountEncryptionService::class);
        $accountNumber = 'ACC' . uniqid();
        $encryptionData = $accountEncryptionService->encryptAccountNumber($accountNumber);
        
        $account->setAccountNumberEnc($encryptionData['encrypted']);
        $account->setAccountNumberHash($encryptionData['hash']);
        $account->setCurrency($currency);
        $account->setBalance($balance);

        $this->entityManager->persist($account);
        return $account;
    }

    private function createTestCurrencyRate(string $baseCurrency, string $targetCurrency, string $rate): CurrencyRate
    {
        $currencyRate = new CurrencyRate();
        $currencyRate->setBaseCurrency($baseCurrency);
        $currencyRate->setTargetCurrency($targetCurrency);
        $currencyRate->setRate($rate);

        $this->entityManager->persist($currencyRate);
        return $currencyRate;
    }
}