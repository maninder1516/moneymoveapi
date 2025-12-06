<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Api\V1;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Entity\LedgerEntry;
use App\Enum\LedgerEntryType;
use App\Service\Api\V1\LedgerService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for LedgerService.
 * 
 * Tests the double-entry bookkeeping functionality including:
 * - Ledger entry creation for transfers
 * - Proper debit and credit entry generation
 * - Transaction association with ledger entries
 * 
 * @covers LedgerService
 */
class LedgerServiceTest extends TestCase
{
    private LedgerService $ledgerService;
    private EntityManagerInterface|MockObject $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->ledgerService = new LedgerService($this->entityManager);
    }

    public function testCreateTransferLedgerEntriesWithSameCurrency(): void
    {
        // Arrange
        $fromAccount = $this->createMockAccount(1, 'USD');
        $toAccount = $this->createMockAccount(2, 'USD');
        $transaction = $this->createMockTransaction($fromAccount, $toAccount, '100.00', 'USD');
        
        $conversionResult = [
            'converted_amount' => '100.00',
            'exchange_rate' => '1.0',
            'requires_conversion' => false
        ];

        $this->setupEntityManagerExpectations(2); // Two ledger entries

        // Act
        $this->ledgerService->createTransferLedgerEntries($transaction, $conversionResult);

        // Assert - Verified through mock expectations
        $this->assertTrue(true); // Test passes if no exceptions thrown
    }

    public function testCreateTransferLedgerEntriesWithCurrencyConversion(): void
    {
        // Arrange
        $fromAccount = $this->createMockAccount(1, 'USD');
        $toAccount = $this->createMockAccount(2, 'EUR');
        $transaction = $this->createMockTransaction($fromAccount, $toAccount, '100.00', 'USD', '92.00', 'EUR');
        
        $conversionResult = [
            'converted_amount' => '92.00',
            'exchange_rate' => '0.92',
            'requires_conversion' => true
        ];

        $this->setupEntityManagerExpectations(2); // Two ledger entries

        // Act
        $this->ledgerService->createTransferLedgerEntries($transaction, $conversionResult);

        // Assert - Verified through mock expectations
        $this->assertTrue(true); // Test passes if no exceptions thrown
    }

    public function testCreateLedgerEntryFields(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->ledgerService);
        $method = $reflection->getMethod('createLedgerEntry');
        $method->setAccessible(true);

        // Arrange
        $transaction = $this->createMockTransaction(
            $this->createMockAccount(1, 'USD'),
            $this->createMockAccount(2, 'USD'),
            '100.00',
            'USD'
        );
        $account = $this->createMockAccount(1, 'USD');

        // Create a real LedgerEntry to test field setting
        $ledgerEntry = new LedgerEntry();
        
        // Mock EntityManager to capture the persist call
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($entry) use ($transaction, $account) {
                return $entry instanceof LedgerEntry &&
                       $entry->getTransaction() === $transaction &&
                       $entry->getAccount() === $account &&
                       $entry->getEntryType() === LedgerEntryType::DEBIT &&
                       $entry->getAmount() === '100.00' &&
                       $entry->getCurrency() === 'USD' &&
                       str_contains($entry->getNote(), 'Test debit');
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // Act
        $result = $method->invoke(
            $this->ledgerService,
            $transaction,
            $account,
            LedgerEntryType::DEBIT,
            '100.00',
            'USD',
            'Test debit entry'
        );

        // Assert
        $this->assertInstanceOf(LedgerEntry::class, $result);
    }

    private function createMockAccount(int $id, string $currency): Account|MockObject
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($id);
        $account->method('getCurrency')->willReturn($currency);
        
        return $account;
    }

    private function createMockTransaction(
        Account $fromAccount,
        Account $toAccount,
        string $amount,
        string $currency,
        ?string $convertedAmount = null,
        ?string $convertedCurrency = null
    ): Transaction|MockObject {
        $transaction = $this->createMock(Transaction::class);
        $transaction->method('getFromAccount')->willReturn($fromAccount);
        $transaction->method('getToAccount')->willReturn($toAccount);
        $transaction->method('getAmount')->willReturn($amount);
        $transaction->method('getCurrency')->willReturn($currency);
        $transaction->method('getConvertedAmount')->willReturn($convertedAmount);
        $transaction->method('getConvertedCurrency')->willReturn($convertedCurrency);
        $transaction->method('getReferenceId')->willReturn('TXN-TEST-123');
        
        return $transaction;
    }

    private function setupEntityManagerExpectations(int $persistCallCount): void
    {
        $this->entityManager->expects($this->exactly($persistCallCount))
            ->method('persist')
            ->with($this->isInstanceOf(LedgerEntry::class));

        $this->entityManager->expects($this->exactly($persistCallCount))
            ->method('flush');
    }
}