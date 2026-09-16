<?php

namespace App\Support;

// Default artwork shown wherever a record has no image of its own. Served by
// PlaceholderController so web and mobile clients share one URL.
class Placeholder
{
    public const KINDS = ['product', 'promo', 'category', 'cafe', 'user'];

    public static function url(string $kind = 'product'): string
    {
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'product';

        return "/api/v1/placeholder/{$kind}.svg";
    }

    public static function svg(string $kind = 'product'): string
    {
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'product';

        [$w, $h] = $kind === 'promo' ? [1200, 500] : [600, 600];
        $art = match ($kind) {
            'promo' => self::promoArt(),
            'category' => self::categoryArt(),
            'cafe' => self::storeArt(),
            'user' => self::userArt(),
            default => self::cupArt(),
        };

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}" role="img" aria-label="{$kind} placeholder">
          <rect width="{$w}" height="{$h}" fill="#f1f5f9"/>
          <g transform="translate({$w}, 0)"><circle r="150" fill="#0f766e" opacity="0.07"/></g>
          <g transform="translate(0, {$h})"><circle r="120" fill="#0f766e" opacity="0.06"/></g>
          {$art}
        </svg>
        SVG;
    }

    private static function cupArt(): string
    {
        return <<<'SVG'
        <g transform="translate(300 300)" fill="none" stroke="#0f766e" stroke-width="12" stroke-linecap="round" stroke-linejoin="round" opacity="0.65">
          <path d="M-110 -40 h190 v70 a95 95 0 0 1 -95 95 a95 95 0 0 1 -95 -95 z" fill="#ccfbf1"/>
          <path d="M80 -10 h35 a45 45 0 0 1 0 90 h-20"/>
          <path d="M-70 -90 c0 -25 25 -25 25 -50"/>
          <path d="M-15 -90 c0 -25 25 -25 25 -50"/>
          <path d="M40 -90 c0 -25 25 -25 25 -50"/>
          <path d="M-150 150 h300"/>
        </g>
        SVG;
    }

    private static function promoArt(): string
    {
        return <<<'SVG'
        <g transform="translate(600 250)" fill="none" stroke="#b45309" stroke-width="12" stroke-linecap="round" stroke-linejoin="round" opacity="0.6">
          <rect x="-190" y="-95" width="380" height="190" rx="24" fill="#fef3c7"/>
          <path d="M-190 -35 h380"/>
          <path d="M0 -95 v190"/>
          <path d="M0 -35 c-60 0 -85 -60 -40 -60 c30 0 40 35 40 60 c0 -25 10 -60 40 -60 c45 0 20 60 -40 60z" fill="#fff7ed"/>
        </g>
        SVG;
    }

    private static function categoryArt(): string
    {
        return <<<'SVG'
        <g transform="translate(300 300)" fill="none" stroke="#1d4ed8" stroke-width="12" stroke-linejoin="round" opacity="0.6">
          <rect x="-140" y="-120" width="280" height="240" rx="22" fill="#dbeafe"/>
          <path d="M-140 -40 h280"/>
          <path d="M-55 -120 v80"/>
          <path d="M55 -120 v80"/>
        </g>
        SVG;
    }

    private static function storeArt(): string
    {
        return <<<'SVG'
        <g transform="translate(300 300)" fill="none" stroke="#15803d" stroke-width="12" stroke-linejoin="round" opacity="0.6">
          <path d="M-150 -60 l40 -70 h220 l40 70z" fill="#dcfce7"/>
          <path d="M-125 -60 v170 h250 v-170"/>
          <rect x="-45" y="10" width="90" height="100" rx="8" fill="#ffffff"/>
        </g>
        SVG;
    }

    private static function userArt(): string
    {
        return <<<'SVG'
        <g transform="translate(300 300)" fill="none" stroke="#9333ea" stroke-width="12" stroke-linejoin="round" opacity="0.6">
          <circle cy="-60" r="70" fill="#f3e8ff"/>
          <path d="M-130 130 a130 130 0 0 1 260 0" fill="#f3e8ff"/>
        </g>
        SVG;
    }
}
