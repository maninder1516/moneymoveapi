<?php

declare(strict_types=1);

namespace App\Service\Api\V1;

use App\Dto\Api\V1\UserDto;
use App\Exception\Api\UserNotFoundException;
use App\Repository\UserRepository;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    public function findAll(int $page = 1, int $limit = 10): array
    {
        $users = $this->userRepository->findWithPagination($page, $limit);
        
        return array_map(function($user) {
            return new UserDto(
                id: $user->getId(),
                name: $user->getName(),
                email: $user->getEmail(),
                username: $user->getActualUsername(),
                createdAt: $user->getCreatedAt()
            );
        }, $users);
    }

    public function findById(int $id): UserDto
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new UserNotFoundException("User with ID {$id} not found");
        }
        
        return new UserDto(
            id: $user->getId(),
            name: $user->getName(),
            email: $user->getEmail(),
            username: $user->getActualUsername(),
            createdAt: $user->getCreatedAt()
        );
    }

    public function create(array $data): UserDto
    {
        $user = $this->userRepository->createUser($data);
        
        return new UserDto(
            id: $user->getId(),
            name: $user->getName(),
            email: $user->getEmail(),
            username: $user->getActualUsername(),
            createdAt: $user->getCreatedAt()
        );
    }

    public function update(int $id, array $data): UserDto
    {
        $user = $this->userRepository->updateUser($id, $data);
        
        return new UserDto(
            id: $user->getId(),
            name: $user->getName(),
            email: $user->getEmail(),
            username: $user->getActualUsername(),
            createdAt: $user->getCreatedAt()
        );
    }

    public function delete(int $id): void
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new UserNotFoundException("User with ID {$id} not found");
        }
        
        $this->userRepository->deleteUser($user);
    }

    public function getTotalCount(): int
    {
        return $this->userRepository->getTotalUserCount();
    }
}