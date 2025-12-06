<?php

declare(strict_types=1);

namespace App\Exception\Api;

class InsufficientBalanceException extends \Exception
{
    public function __construct(string $message = 'Insufficient balance', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}