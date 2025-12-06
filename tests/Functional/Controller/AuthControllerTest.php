<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for AuthController API endpoints.
 * 
 * Tests the complete HTTP request/response cycle for authentication including:
 * - User registration
 * - User login with JWT token generation
 * - Authentication error handling
 * 
 * @covers AuthController
 * @group functional
 */
class AuthControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient([
            'SERVER_NAME' => 'moneymoveapi.com'
        ]);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        // Start transaction for test data isolation
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

    public function testUserRegistration(): void
    {
        $registrationData = [
            'name' => 'Test User',
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => 'test123'
        ];

        $this->client->request(
            'POST',
            '/api/v1/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($registrationData)
        );

        $this->assertEquals(201, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($responseData['success']);
        $this->assertEquals('User registered successfully', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('user', $responseData['data']);
        $this->assertEquals($registrationData['name'], $responseData['data']['user']['name']);
        $this->assertEquals($registrationData['email'], $responseData['data']['user']['email']);
        
        // Verify user exists in database
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $registrationData['email']]);
        $this->assertNotNull($user);
        $this->assertEquals($registrationData['name'], $user->getName());
        $this->assertEquals($registrationData['email'], $user->getEmail());
    }

    public function testUserRegistrationWithMissingFields(): void
    {
        $registrationData = [
            'email' => 'test@example.com'
            // Missing name and password
        ];

        $this->client->request(
            'POST',
            '/api/v1/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($registrationData)
        );

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Missing required fields', $responseData['message']);
    }

    public function testUserRegistrationWithDuplicateEmail(): void
    {
        $email = 'duplicate_' . uniqid() . '@example.com';
        
        // Create first user
        $registrationData = [
            'name' => 'First User',
            'email' => $email,
            'password' => 'test123'
        ];

        $this->client->request(
            'POST',
            '/api/v1/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($registrationData)
        );

        $this->assertEquals(201, $this->client->getResponse()->getStatusCode());

        // Try to create second user with same email
        $duplicateData = [
            'name' => 'Second User',
            'email' => $email,
            'password' => 'test456'
        ];

        $this->client->request(
            'POST',
            '/api/v1/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($duplicateData)
        );

        $this->assertEquals(409, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('already exists', $responseData['message']);
    }

    public function testUserLogin(): void
    {
        // Use existing test user from database (created in test setup)
        $loginData = [
            'username' => 'testuser@example.com',
            'password' => 'test123'
        ];

        $this->client->request(
            'POST',
            '/api/v1/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($loginData)
        );

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        
        // JWT authentication success handler returns standard JWT format
        $this->assertArrayHasKey('token', $responseData);
        $this->assertNotEmpty($responseData['token']);
        
        // Verify the token structure (should be a valid JWT)
        $tokenParts = explode('.', $responseData['token']);
        $this->assertCount(3, $tokenParts, 'JWT token should have 3 parts separated by dots');
    }

    public function testUserLoginWithInvalidCredentials(): void
    {
        $loginData = [
            'username' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ];

        $this->client->request(
            'POST',
            '/api/v1/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($loginData)
        );

        $this->assertEquals(401, $this->client->getResponse()->getStatusCode());
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(401, $responseData['code']);
        $this->assertStringContainsString('Invalid credentials', $responseData['message']);
    }

    public function testUserLoginWithMissingFields(): void
    {
        $loginData = [
            'username' => 'test@example.com'
            // Missing password
        ];

        $this->client->request(
            'POST',
            '/api/v1/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($loginData)
        );

        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }
}