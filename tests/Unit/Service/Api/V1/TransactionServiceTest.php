<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Api\V1;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Exception\Api\InsufficientBalanceException;
use App\Service\Api\V1\TransactionService;
use App\Service\Api\V1\CurrencyService;
use App\Service\Api\V1\LedgerService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for TransactionService.
 * 
 * Tests the transaction processing logic including:
 * - Currency conversion handling
 * - Balance validation
 * - Transaction creation and completion
 * - Error handling and rollbacks
 * 
 * @covers TransactionService
 */
class TransactionServiceTest extends TestCase
{
    private TransactionService $transactionService;
    private EntityManagerInterface|MockObject $entityManager;
    private CurrencyService|MockObject $currencyService;
    private LedgerService|MockObject $ledgerService;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->currencyService = $this->createMock(CurrencyService::class);
        $this->ledgerService = $this->createMock(LedgerService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->transactionService = new TransactionService(
            $this->entityManager,
            $this->currencyService,
            $this->ledgerService,
            $this->logger
        );
    }

    public function testProcessTransferWithSameCurrency(): void
    {
        // Arrange
        $fromAccount = $this->createMockAccount(1, 'USD', '1000.00');
        $toAccount = $this->createMockAccount(2, 'USD', '500.00');
        $amount = '100.00';

        $fromAccount->method('hasBalance')
            ->with($amount)
            ->willReturn(true);

        $this->setupEntityManagerMocks();
        $this->setupCurrencyServiceMocks($amount, 'USD', 'USD', false);
        $this->setupLedgerServiceMocks();

        // Act
        $result = $this->transactionService->processTransfer($fromAccount, $toAccount, $amount);

        // Assert
        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals($amount, $result->getAmount());
        $this->assertEquals('USD', $result->getCurrency());
        $this->assertEquals(TransactionStatus::COMPLETED, $result->getStatus());
    }

    public function testProcessTransferWithCurrencyConversion(): void
    {
        // Arrange
        $fromAccount = $this->createMockAccount(1, 'USD', '1000.00');
        $toAccount = $this->createMockAccount(2, 'EUR', '500.00');
        $amount = '100.00';

        $fromAccount->method('hasBalance')
            ->with($amount)
            ->willReturn(true);
        $convertedAmount = '92.00';
        $exchangeRate = '0.92';

        $this->setupEntityManagerMocks();
        $this->setupCurrencyServiceMocks($amount, 'USD', 'EUR', true, $convertedAmount, $exchangeRate);
        $this->setupLedgerServiceMocks();

        // Act
        $result = $this->transactionService->processTransfer($fromAccount, $toAccount, $amount);

        // Assert
        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals($amount, $result->getAmount());
        $this->assertEquals('USD', $result->getCurrency());
        $this->assertEquals($convertedAmount, $result->getConvertedAmount());
        $this->assertEquals('EUR', $result->getConvertedCurrency());
        $this->assertEquals($exchangeRate, $result->getRateUsed());
    }

    public function testProcessTransferWithInsufficientBalance(): void
    {
        // Arrange
        $fromAccount = $this->createMockAccount(1, 'USD', '50.00'); // Insufficient balance
        $toAccount = $this->createMockAccount(2, 'USD', '500.00');
        $amount = '100.00';

        $fromAccount->method('hasBalance')
            ->with($amount)
            ->willReturn(false);

        $this->entityManager->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager->expects($this->once())
            ->method('rollback');

        // Act & Assert
        $this->expectException(InsufficientBalanceException::class);
        $this->transactionService->processTransfer($fromAccount, $toAccount, $amount);
    }

    public function testProcessTransferWithCurrencyServiceException(): void
    {
        // Arrange
        $fromAccount = $this->createMockAccount(1, 'USD', '1000.00');
        $toAccount = $this->createMockAccount(2, 'XYZ', '500.00'); // Invalid currency
        $amount = '100.00';

        $fromAccount->method('hasBalance')
            ->with($amount)
            ->willReturn(true);

        $this->entityManager->expects($this->once())
            ->method('beginTransaction');

        $this->currencyService->expects($this->once())
            ->method('convertAmount')
            ->willThrowException(new \RuntimeException('No exchange rate found for USD to XYZ'));

        $this->entityManager->expects($this->once())
            ->method('rollback');

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No exchange rate found for USD to XYZ');
        $this->transactionService->processTransfer($fromAccount, $toAccount, $amount);
    }

    public function testGenerateTransactionId(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->transactionService);
        $method = $reflection->getMethod('generateTransactionId');
        $method->setAccessible(true);

        $transactionId = $method->invoke($this->transactionService);

        $this->assertStringStartsWith('TXN-', $transactionId);
        $this->assertEquals(25, strlen($transactionId)); // TXN- (4) + YmdHis timestamp (14) + - (1) + unique (6)
    }

    private function createMockAccount(int $id, string $currency, string $balance, bool $hasBalance = true): Account|MockObject
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($id);
        $account->method('getCurrency')->willReturn($currency);
        $account->method('getBalance')->willReturn($balance);
        $account->method('setBalance')->willReturnSelf();

        return $account;
    }

    private function setupEntityManagerMocks(): void
    {
        $this->entityManager->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager->expects($this->once())
            ->method('commit');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');
    }

    private function setupCurrencyServiceMocks(
        string $amount,
        string $fromCurrency,
        string $toCurrency,
        bool $requiresConversion,
        string $convertedAmount = null,
        string $exchangeRate = null
    ): void {
        if ($requiresConversion) {
            $this->currencyService->expects($this->once())
                ->method('convertAmount')
                ->with($amount, $fromCurrency, $toCurrency)
                ->willReturn([
                    'converted_amount' => $convertedAmount ?? $amount,
                    'exchange_rate' => $exchangeRate ?? '1.0'
                ]);
        } else {
            $this->currencyService->expects($this->never())
                ->method('convertAmount');
        }
    }

    private function setupLedgerServiceMocks(): void
    {
        $this->ledgerService->expects($this->once())
            ->method('createTransferLedgerEntries');
    }
}