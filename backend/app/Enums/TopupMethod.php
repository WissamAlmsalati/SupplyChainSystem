<?php

namespace App\Enums;

enum TopupMethod: string
{
    // Customer request with a receipt, approved by an admin.
    case BankTransfer = 'bank_transfer';
    // Cash handed to a delegate; credited immediately.
    case DelegateCash = 'delegate_cash';
    // Online checkout; credited by the gateway callback.
    case Gateway = 'gateway';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
