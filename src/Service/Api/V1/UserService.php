<?php

declare(strict_types=1);

namespace App\Service\Api\V1;

use App\Dto\Api\V1\UserDto;
use App\Exception\Api\UserNotFoundException;

class UserService
{
    public function __construct(
        // Inject dependencies like EntityManager, repositories, etc.
    ) {
    }

    public function findAll(int $page = 1, int $limit = 10): array
    {
        // TODO: Implement pagination logic with repository
        // $offset = ($page - 1) * $limit;
        // return $this->userRepository->findBy([], null, $limit, $offset);
        
        return []; // Mock return
    }

    public function findById(int $id): UserDto
    {
        // TODO: Implement user finding logic
        // $user = $this->userRepository->find($id);
        // if (!$user) {
        //     throw new UserNotFoundException("User with ID {$id} not found");
        // }
        
        // return UserDto::fromEntity($user);
        
        throw new UserNotFoundException("User with ID {$id} not found");
    }

    public function create(array $data): UserDto
    {
        // TODO: Implement user creation logic
        // Validate, create entity, persist, etc.
        
        return new UserDto(); // Mock return
    }

    public function update(int $id, array $data): UserDto
    {
        // TODO: Implement user update logic
        
        return new UserDto(); // Mock return
    }

    public function delete(int $id): void
    {
        // TODO: Implement user deletion logic
    }

    public function getTotalCount(): int
    {
        // TODO: Return total count from repository
        return 0;
    }
}