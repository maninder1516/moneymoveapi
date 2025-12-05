<?php

declare(strict_types=1);

namespace App\Enum;

enum TransactionType: string
{
    case TRANSFER = 'transfer';
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';
}