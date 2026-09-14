<?php

namespace App\Enums;

enum FeaturedSectionSource: string
{
    // Products picked and ordered by the admin.
    case Manual = 'manual';
    // Products selected automatically by filters and a sort (best sellers, cheapest…).
    case Filter = 'filter';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
