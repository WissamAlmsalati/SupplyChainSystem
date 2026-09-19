<?php

namespace App\Support;

use NumberFormatter;

/**
 * An amount written out in Arabic, the way a Libyan invoice closes:
 * "فقط ثلاثمائة وخمسة وستون ديناراً وخمسمائة درهم لا غير".
 *
 * The words come from ICU's Arabic spell-out rules; this only joins the
 * hundreds the way they are written (ثلاثمائة, not ثلاثة مائة) and names the
 * currency. One dinar is a thousand dirhams, and amounts are kept to two
 * decimals, so .50 is five hundred dirhams.
 */
final class MoneyInWords
{
    private const HUNDREDS = [
        'ثلاثة مائة' => 'ثلاثمائة', 'أربعة مائة' => 'أربعمائة', 'خمسة مائة' => 'خمسمائة', 'ستة مائة' => 'ستمائة',
        'سبعة مائة' => 'سبعمائة', 'ثمانية مائة' => 'ثمانمائة', 'تسعة مائة' => 'تسعمائة',
        // ICU's spellings of 11, 12 and 2 that Arabic print does not use.
        'إحدى عشر' => 'أحد عشر', 'إثنا عشر' => 'اثنا عشر', 'إثنان' => 'اثنان',
    ];

    public static function dinars(float|string $amount): string
    {
        $cents = (int) round(abs((float) $amount) * 100);
        $dinars = intdiv($cents, 100);
        $dirhams = ($cents % 100) * 10;

        if (! class_exists(NumberFormatter::class)) {
            return number_format($cents / 100, 2).' د.ل';
        }

        $formatter = new NumberFormatter('ar', NumberFormatter::SPELLOUT);
        // Dinar and dirham are masculine nouns; the default rules count in the feminine.
        $formatter->setTextAttribute(NumberFormatter::DEFAULT_RULESET, '%spellout-cardinal-masculine');
        $spell = fn (int $n) => strtr($formatter->format($n), self::HUNDREDS);

        // One and two are said with the noun alone: "دينار واحد", "ديناران".
        $count = fn (int $n, array $u) => match ($n) {
            1 => $u[0].' واحد',
            2 => $u[1],
            default => $spell($n).' '.self::unit($n, ...$u),
        };

        $parts = [];
        if ($dinars > 0 || $dirhams === 0) {
            $parts[] = $count($dinars, ['دينار', 'ديناران', 'دنانير', 'ديناراً']);
        }
        if ($dirhams > 0) {
            $parts[] = $count($dirhams, ['درهم', 'درهمان', 'دراهم', 'درهماً']);
        }

        return 'فقط '.implode(' و', $parts).' لا غير';
    }

    // Arabic counts its nouns four ways: one, two, three to ten, and the rest.
    private static function unit(int $n, string $one, string $two, string $few, string $many): string
    {
        $last = $n % 100;

        return match (true) {
            $n === 1 => $one,
            $n === 2 => $two,
            $last >= 3 && $last <= 10 => $few,
            $last >= 11 => $many,
            default => $one, // 100, 200, 1000: counted as the singular
        };
    }
}
