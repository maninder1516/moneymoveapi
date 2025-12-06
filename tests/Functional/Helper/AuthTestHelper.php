<?php

declare(strict_types=1);

namespace App\Tests\Functional\Helper;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Helper class for functional tests that need authenticated users
 */
class AuthTestHelper
{
    public static function createAuthenticatedUser(
        KernelBrowser $client, 
        EntityManagerInterface $entityManager,
        string $email = null,
        string $password = 'test123'
    ): array {
        $email = $email ?: 'test_' . uniqid() . '@example.com';
        
        // Register user
        $registrationData = [
            'name' => 'Test User',
            'email' => $email,
            'password' => $password
        ];

        $client->request(
            'POST',
            '/api/v1/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($registrationData)
        );

        if ($client->getResponse()->getStatusCode() !== 201) {
            throw new \RuntimeException('Failed to register test user: ' . $client->getResponse()->getContent());
        }

        // Login to get token
        $loginData = [
            'username' => $email,
            'password' => $password
        ];

        $client->request(
            'POST',
            '/api/v1/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($loginData)
        );

        if ($client->getResponse()->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to login test user: ' . $client->getResponse()->getContent());
        }

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $token = $responseData['data']['access_token'];

        // Get user entity
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        return [
            'user' => $user,
            'token' => $token,
            'email' => $email
        ];
    }
}