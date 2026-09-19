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
    // Cash handed back to a customer for returned goods, out of what the delegate holds.
    case RefundPayout = 'refund_payout';
    case Adjustment = 'adjustment';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
