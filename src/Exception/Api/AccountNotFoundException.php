<?php

declare(strict_types=1);

namespace App\Exception\Api;

class AccountNotFoundException extends \Exception
{
    public function __construct(string $message = 'Account not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}