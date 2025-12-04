<?php

declare(strict_types=1);

namespace App\Dto\Api\V1;

readonly class UserDto
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $email = null,
        public ?\DateTimeInterface $createdAt = null,
        public ?\DateTimeInterface $updatedAt = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTime($data['updated_at']) : null
        );
    }

    // If using Doctrine entities, you can add a fromEntity method
    // public static function fromEntity(User $user): self
    // {
    //     return new self(
    //         id: $user->getId(),
    //         name: $user->getName(),
    //         email: $user->getEmail(),
    //         createdAt: $user->getCreatedAt(),
    //         updatedAt: $user->getUpdatedAt()
    //     );
    // }
}