<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
