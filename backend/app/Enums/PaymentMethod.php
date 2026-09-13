<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    // Paid in full from the customer's wallet at checkout.
    case Wallet = 'wallet';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
