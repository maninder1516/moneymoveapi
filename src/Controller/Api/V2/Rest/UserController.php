<?php

declare(strict_types=1);

namespace App\Controller\Api\V2\Rest;

use App\Controller\Api\V2\BaseV2Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users', name: 'users_')]
class UserController extends BaseV2Controller
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // V2 might have different response format, additional fields, etc.
        return $this->json([
            'success' => true,
            'version' => '2.0',
            'message' => 'Users List V2 - Enhanced with new features!',
            'data' => [
                'users' => [
                    [
                        'id' => 1, 
                        'name' => 'John Doe', 
                        'email' => 'john@example.com',
                        'profile_image' => 'https://example.com/avatar1.jpg', // New in V2
                        'last_login' => '2025-12-04T10:30:00Z' // New in V2
                    ]
                ]
            ],
            'metadata' => [ // New in V2
                'api_version' => '2.0',
                'response_time' => '45ms',
                'server_time' => (new \DateTime())->format('c')
            ]
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        return $this->json([
            'success' => true,
            'version' => '2.0',
            'message' => 'User details V2',
            'data' => [
                'id' => $id,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'profile_image' => 'https://example.com/avatar1.jpg',
                'preferences' => [ // New detailed preferences in V2
                    'theme' => 'dark',
                    'notifications' => true,
                    'language' => 'en'
                ]
            ]
        ]);
    }
}