<?php

namespace App\Enums;

enum CartType: string
{
    // The customer's single working cart; emptied on checkout.
    case Shopping = 'shopping';
    // A named, reusable cart the customer re-orders from.
    case Recurring = 'recurring';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
