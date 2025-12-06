<?php

declare(strict_types=1);

namespace App\Dto\Api\V1;

readonly class AccountDto
{
    public function __construct(
        public ?int $id = null,
        public ?string $accountNumber = null,
        public ?string $balance = null,
        public ?string $currency = null,
        public ?int $userId = null,
        public ?string $userName = null,
        public ?\DateTimeInterface $createdAt = null,
        public ?\DateTimeInterface $updatedAt = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'account_number' => $this->accountNumber,
            'balance' => $this->balance,
            'currency' => $this->currency,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            accountNumber: $data['account_number'] ?? null,
            balance: $data['balance'] ?? null,
            currency: $data['currency'] ?? null,
            userId: $data['user_id'] ?? null,
            userName: $data['user_name'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTime($data['updated_at']) : null
        );
    }
}