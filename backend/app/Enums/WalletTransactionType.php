<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case TopUp = 'topup';
    case Payment = 'payment';
    case Refund = 'refund';
    case Adjustment = 'adjustment';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
