<?php

namespace App\Enums;

/**
 * Where a banner sends the customer inside the apps. The set is closed on
 * purpose: a destination the apps cannot route is a banner that does nothing
 * when it is tapped, so the office is stopped from creating one.
 */
enum PromoDestination: string
{
    case Home = 'home';
    case Products = 'products';
    case Product = 'product';
    case Category = 'category';
    case Orders = 'orders';
    case Cart = 'cart';
    case Profile = 'profile';

    /** Destinations that name one record, and so need an id. */
    public function needsId(): bool
    {
        return in_array($this, [self::Product, self::Category], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Home => 'الرئيسية',
            self::Products => 'صفحة المنتجات',
            self::Product => 'منتج محدد',
            self::Category => 'تصنيف محدد',
            self::Orders => 'الطلبات',
            self::Cart => 'سلة المشتريات',
            self::Profile => 'الملف الشخصي',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function needingId(): array
    {
        return array_map(fn (self $c) => $c->value, array_filter(self::cases(), fn (self $c) => $c->needsId()));
    }
}
