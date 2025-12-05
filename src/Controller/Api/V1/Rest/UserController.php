<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Rest;

use App\Controller\Api\V1\BaseV1Controller;
use App\Dto\Api\V1\CreateUserRequestDto;
use App\Service\Api\ApiConfigService;
use App\Service\Api\V1\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users', name: 'users_')]
class UserController extends BaseV1Controller
{
    public function __construct(
        ApiConfigService $apiConfig,
        private readonly UserService $userService
    ) {
        parent::__construct($apiConfig);
    }
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 10)));

        $users = $this->userService->findAll($page, $limit);
        $total = $this->userService->getTotalCount();

        return $this->paginatedResponse(
            array_map(fn($user) => $user->toArray(), $users),
            $total,
            $page,
            $limit
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->findById($id);

        return $this->successResponse($user->toArray());
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        try {
            $requestDto = CreateUserRequestDto::fromArray($data);
            $user = $this->userService->create($requestDto->toArray());

            return $this->successResponse($user->toArray(), 'User created successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        // Get JSON content
        $data = json_decode($request->getContent(), true);

        // TODO: Validate data
        // TODO: Call service to update user
        
        // Mock response
        $updatedUser = array_merge($data, ['id' => $id]);

        return $this->successResponse($updatedUser, 'User updated successfully');
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        // TODO: Call service to delete user
        
        return $this->successResponse(null, 'User deleted successfully');
    }
}