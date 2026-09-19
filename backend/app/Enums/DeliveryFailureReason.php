<?php

namespace App\Enums;

// Why a driver could not hand an order over. A closed list, so the office can
// count them; "other" carries a written note.
enum DeliveryFailureReason: string
{
    case CustomerAbsent = 'customer_absent';
    case Unreachable = 'unreachable';
    case WrongAddress = 'wrong_address';
    case Refused = 'refused';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CustomerAbsent => 'الزبون غير موجود في العنوان',
            self::Unreachable => 'تعذّر الاتصال بالزبون',
            self::WrongAddress => 'العنوان غير صحيح',
            self::Refused => 'الزبون رفض الاستلام',
            self::Other => 'سبب آخر',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return array_column(array_map(fn (self $r) => ['k' => $r->value, 'v' => $r->label()], self::cases()), 'v', 'k');
    }
}
