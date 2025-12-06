<?php

declare(strict_types=1);

namespace App\Dto\Api\V1;

use Symfony\Component\Validator\Constraints as Assert;

readonly class TransferRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'To account number is required')]
        public ?string $toAccountNumber = null,

        #[Assert\NotBlank(message: 'Amount is required')]
        #[Assert\Positive(message: 'Amount must be positive')]
        #[Assert\Regex('/^\d+(\.\d{1,2})?$/', message: 'Invalid amount format')]
        public ?string $amount = null,

        public ?string $note = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            toAccountNumber: $data['to_account_number'] ?? null,
            amount: $data['amount'] ?? null,
            note: $data['note'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'to_account_number' => $this->toAccountNumber,
            'amount' => $this->amount,
            'note' => $this->note,
        ];
    }
}