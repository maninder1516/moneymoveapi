<?php

declare(strict_types=1);

namespace App\Dto\Api\V1;

readonly class CreateUserRequestDto
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            email: $data['email'] ?? '',
            password: $data['password'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}