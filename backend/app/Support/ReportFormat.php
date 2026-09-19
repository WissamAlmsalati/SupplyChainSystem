<?php

namespace App\Support;

// How numbers are written on every printed document, in one place so an
// invoice, a statement and a report never disagree about a minus sign.
final class ReportFormat
{
    public static function money(float|string|null $value): string
    {
        return number_format((float) $value, 2);
    }

    public static function count(int|float|string|null $value): string
    {
        return number_format((float) $value);
    }

    // The accountant's minus: a negative amount goes in brackets, not in red.
    public static function signed(float|string|null $value): string
    {
        $value = (float) $value;

        return $value < 0 ? '('.number_format(abs($value), 2).')' : number_format($value, 2);
    }

    public static function percent(float|string|null $value): string
    {
        return $value === null ? '-' : rtrim(rtrim(number_format((float) $value, 1), '0'), '.').'%';
    }

    public static function minutes(?int $value): string
    {
        if ($value === null) {
            return '-';
        }

        return match (true) {
            $value < 60 => $value.' د',
            $value < 2880 => intdiv($value, 60).' س '.($value % 60).' د',
            // Past two days, minutes are noise.
            default => intdiv($value, 1440).' يوم '.intdiv($value % 1440, 60).' س',
        };
    }

    public static function dash(mixed $value): string
    {
        return $value === null || $value === '' ? '-' : (string) $value;
    }
}
