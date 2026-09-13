<?php

namespace App\Enums;

enum CustodyEntryType: string
{
    // Cash received on delivery of a cash order.
    case OrderCollection = 'order_collection';
    // Cash received to top up a customer's wallet.
    case WalletCollection = 'wallet_collection';
    // Cash handed over to the office.
    case Settlement = 'settlement';
    case Adjustment = 'adjustment';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
