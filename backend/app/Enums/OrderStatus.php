<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Received = 'received';
    case CancellationRequested = 'cancellation_requested';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Confirmed => 'مؤكد',
            self::Preparing => 'قيد التجهيز',
            self::OutForDelivery => 'في الطريق',
            self::Delivered => 'تم التوصيل',
            self::Received => 'مستلم',
            self::CancellationRequested => 'طلب إلغاء',
            self::Cancelled => 'ملغي',
        };
    }

    /** Status values behind each order tab in the apps. */
    public static function groups(): array
    {
        return [
            'active' => [self::Pending->value, self::Confirmed->value, self::Preparing->value, self::OutForDelivery->value, self::CancellationRequested->value],
            'completed' => [self::Delivered->value, self::Received->value],
            'cancelled' => [self::Cancelled->value],
        ];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
