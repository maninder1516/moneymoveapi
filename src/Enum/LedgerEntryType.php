<?php

declare(strict_types=1);

namespace App\Enum;

enum LedgerEntryType: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';
}