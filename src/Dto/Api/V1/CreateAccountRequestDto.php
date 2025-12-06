<?php

declare(strict_types=1);

namespace App\Dto\Api\V1;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateAccountRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Currency is required')]
        #[Assert\Length(exactly: 3, exactMessage: 'Currency must be exactly 3 characters')]
        #[Assert\Choice(choices: ['INR', 'USD', 'EUR', 'GBP'], message: 'Invalid currency')]
        public ?string $currency = 'INR'
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            currency: $data['currency'] ?? 'INR'
        );
    }

    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
        ];
    }
}