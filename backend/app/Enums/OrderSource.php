<?php

namespace App\Enums;

enum OrderSource: string
{
    case App = 'app';
    case Dashboard = 'dashboard';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
