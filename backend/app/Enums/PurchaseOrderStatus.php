<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    // Receiving adds the items to warehouse stock; it cannot be undone.
    case Received = 'received';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
