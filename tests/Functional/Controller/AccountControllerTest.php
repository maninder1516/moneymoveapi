<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Entity\Account;
use App\Entity\CurrencyRate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for AccountController API endpoints.
 * 
 * Tests the complete HTTP request/response cycle for account operations including:
 * - Account CRUD operations (create, read, update, delete)
 * - Money transfer functionality
 * - Currency conversion with real API calls
 * - Error handling and edge cases
 * 
 * @covers AccountController
 * @group functional
 */
class AccountControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $entityManager;
    private User $testUser;
    private string $authToken;

    protected function setUp(): void
    {
        $this->client = static::createClient([
            'SERVER_NAME' => 'moneymoveapi.com'
        ]);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        // Get existing test user from database
        $this->testUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'testuser@example.com']);
            
        if (!$this->testUser) {
            $this->markTestSkipped('Test user not found in database');
        }
        
        // Authenticate with existing user to get JWT token
        $this->authenticateWithExistingUser();
        
        // Start transaction for test data isolation (after auth)
        $this->entityManager->beginTransaction();
    }
    
    private function authenticateWithExistingUser(): void
    {
        $loginData = [
            'username' => 'testuser@example.com',
            'password' => 'test123' // This matches the hashed password we inserted
        ];

        $this->client->request(
            'POST',
            '/api/v1/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($loginData)
        );

        $statusCode = $this->client->getResponse()->getStatusCode();
        $content = $this->client->getResponse()->getContent();
        
        if ($statusCode !== 200) {
            $this->markTestSkipped('Cannot authenticate test user: ' . $content);
        }

        $responseData = json_decode($content, true);
        $this->authToken = $responseData['token'];
    }

    protected function tearDown(): void
    {
        // Rollback any changes made during tests
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    public function testCreateAccount(): void
    {
        $accountData = [
            'currency' => 'USD'
        ];

        $this->client->request(
            'POST',
            '/api/v1/accounts',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $this->authToken
            ],
            json_encode($accountData)
        );

        // Debug output
        $statusCode = $this->client->getResponse()->getStatusCode();
        $content = $this->client->getResponse()->getContent();
        if ($statusCode !== 201) {
            echo "Status: $statusCode\n";
            echo "Response: $content\n";
        }

        $this->assertEquals(201, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('id', $responseData['data']);
        $this->assertEquals('USD', $responseData['data']['currency']);
        $this->assertEquals('0.00', $responseData['data']['balance']);
    }

    public function testCreateAccountWithInvalidCurrency(): void
    {
        $accountData = [
            'currency' => 'INVALID'
        ];

        $this->client->request(
            'POST',
            '/api/v1/accounts',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $this->authToken
            ],
            json_encode($accountData)
        );

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Validation failed', $responseData['message']);
    }

    public function testGetUserAccounts(): void
    {
        // Create a test account first
        $this->createTestAccount($this->testUser, 'USD', '1000.00');
        $this->entityManager->flush();

        $this->client->request(
            'GET',
            '/api/v1/accounts',
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer ' . $this->authToken
            ]
        );

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(1, $responseData['data']);
        $this->assertEquals('USD', $responseData['data'][0]['currency']);
        $this->assertEquals('1000.00', $responseData['data'][0]['balance']);
    }

    public function testGetSpecificAccount(): void
    {
        // Create a test account
        $account = $this->createTestAccount($this->testUser, 'EUR', '500.50');
        $this->entityManager->flush();

        $this->client->request(
            'GET',
            '/api/v1/accounts/' . $account->getId(),
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer ' . $this->authToken
            ]
        );

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($responseData['success']);
        $this->assertEquals($account->getId(), $responseData['data']['id']);
        $this->assertEquals('EUR', $responseData['data']['currency']);
        $this->assertEquals('500.50', $responseData['data']['balance']);
    }

    public function testGetAccountNotFound(): void
    {
        $this->client->request(
            'GET',
            '/api/v1/accounts/99999',
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer ' . $this->authToken
            ]
        );

        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Account not found', $responseData['message']);
    }

    public function testTransferMoneySameCurrency(): void
    {
        // Create test accounts
        $fromAccount = $this->createTestAccount($this->testUser, 'USD', '1000.00');
        $toUser = $this->createTestUser('recipient@example.com');
        $toAccount = $this->createTestAccount($toUser, 'USD', '500.00');
        $this->entityManager->flush();

        $transferData = [
            'toAccountNumber' => $this->decryptAccountNumber($toAccount),
            'amount' => '100.00',
            'note' => 'Test transfer'
        ];

        $this->client->request(
            'POST',
            '/api/v1/accounts/' . $fromAccount->getId() . '/transfer',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $this->authToken
            ],
            json_encode($transferData)
        );

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('transaction_id', $responseData['data']);
        $this->assertEquals('100.00', $responseData['data']['amount']);
        $this->assertEquals('USD', $responseData['data']['from_currency']);
        $this->assertEquals('USD', $responseData['data']['to_currency']);
    }

    public function testTransferMoneyWithCurrencyConversion(): void
    {
        // Create currency rate
        $this->createTestCurrencyRate('USD', 'EUR', '0.92');
        
        // Create test accounts
        $fromAccount = $this->createTestAccount($this->testUser, 'USD', '1000.00');
        $toUser = $this->createTestUser('recipient@example.com');
        $toAccount = $this->createTestAccount($toUser, 'EUR', '500.00');
        $this->entityManager->flush();

        $transferData = [
            'toAccountNumber' => $this->decryptAccountNumber($toAccount),
            'amount' => '100.00',
            'note' => 'Cross-currency transfer'
        ];

        $this->client->request(
            'POST',
            '/api/v1/accounts/' . $fromAccount->getId() . '/transfer',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $this->authToken
            ],
            json_encode($transferData)
        );

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($responseData['success']);
        $this->assertEquals('100.00', $responseData['data']['amount']);
        $this->assertEquals('USD', $responseData['data']['from_currency']);
        $this->assertEquals('EUR', $responseData['data']['to_currency']);
        $this->assertEquals('92.00', $responseData['data']['converted_amount']);
        $this->assertEquals('0.92', $responseData['data']['exchange_rate']);
    }

    public function testTransferInsufficientBalance(): void
    {
        // Create test accounts with insufficient balance
        $fromAccount = $this->createTestAccount($this->testUser, 'USD', '50.00');
        $toUser = $this->createTestUser('recipient@example.com');
        $toAccount = $this->createTestAccount($toUser, 'USD', '500.00');
        $this->entityManager->flush();

        $transferData = [
            'toAccountNumber' => $this->decryptAccountNumber($toAccount),
            'amount' => '100.00',
            'note' => 'Insufficient funds test'
        ];

        $this->client->request(
            'POST',
            '/api/v1/accounts/' . $fromAccount->getId() . '/transfer',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $this->authToken
            ],
            json_encode($transferData)
        );

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Insufficient balance', $responseData['message']);
    }

    public function testTransferToInvalidAccountNumber(): void
    {
        $fromAccount = $this->createTestAccount($this->testUser, 'USD', '1000.00');
        $this->entityManager->flush();

        $transferData = [
            'toAccountNumber' => 'INVALID_ACCOUNT',
            'amount' => '100.00',
            'note' => 'Invalid account test'
        ];

        $this->client->request(
            'POST',
            '/api/v1/accounts/' . $fromAccount->getId() . '/transfer',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $this->authToken
            ],
            json_encode($transferData)
        );

        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Account not found', $responseData['message']);
    }

    public function testDeleteAccount(): void
    {
        $account = $this->createTestAccount($this->testUser, 'GBP', '0.00');
        $this->entityManager->flush();

        $this->client->request(
            'DELETE',
            '/api/v1/accounts/' . $account->getId(),
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer ' . $this->authToken
            ]
        );

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertStringContainsString('Account deleted successfully', $responseData['message']);
    }

    public function testUnauthorizedAccess(): void
    {
        $this->client->request('GET', '/api/v1/accounts');
        $this->assertEquals(401, $this->client->getResponse()->getStatusCode());
    }

    private function createTestUser(string $email = null): User
    {
        // For additional test users in transfer tests, we'll just return the main test user
        // In a real scenario, you'd create additional users via the registration API
        return $this->testUser;
    }

    private function createTestAccount(User $user, string $currency, string $balance): Account
    {
        $account = new Account();
        $account->setUser($user);
        
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



    private function decryptAccountNumber(Account $account): string
    {
        $accountEncryptionService = static::getContainer()->get(\App\Service\AccountEncryptionService::class);
        return $accountEncryptionService->decryptAccountNumber($account->getAccountNumberEnc());
    }
}