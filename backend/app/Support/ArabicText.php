<?php

namespace App\Support;

/**
 * Arabic text normalization for search: readers type "احمد" for "أحمد" and
 * "قهوه" for "قهوة", so both the query and the searched columns are folded to
 * one plain form before matching.
 */
class ArabicText
{
    /** Folded pairs applied in order, to both PHP strings and SQL columns. */
    public const FOLD = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ٲ' => 'ا', 'ٳ' => 'ا',
        'ة' => 'ه',
        'ى' => 'ي', 'ئ' => 'ي',
        'ؤ' => 'و',
        'ـ' => '',
        // Harakat and other marks people rarely type.
        "\u{0610}" => '', "\u{064B}" => '', "\u{064C}" => '', "\u{064D}" => '',
        "\u{064E}" => '', "\u{064F}" => '', "\u{0650}" => '', "\u{0651}" => '',
        "\u{0652}" => '', "\u{0653}" => '', "\u{0654}" => '', "\u{0655}" => '',
        "\u{0670}" => '',
        // Arabic-Indic digits, so "١٢٥" finds "125".
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public static function normalize(?string $value): string
    {
        return strtr(mb_strtolower(trim((string) $value)), self::FOLD);
    }

    /**
     * The same folding as a SQL expression, so a plain LIKE matches either
     * spelling. Wraps the column in nested REPLACE() calls (MySQL and SQLite).
     */
    public static function sqlExpression(string $column): string
    {
        $expression = "LOWER({$column})";

        foreach (self::FOLD as $from => $to) {
            $expression = "REPLACE({$expression}, '{$from}', '{$to}')";
        }

        return $expression;
    }
}
