<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case OutForDelivery = 'out_for_delivery';
    // The driver went and could not hand it over; it waits for another attempt or a decision.
    case DeliveryFailed = 'delivery_failed';
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
            self::DeliveryFailed => 'تعذّر التوصيل',
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
            'active' => [self::Pending->value, self::Confirmed->value, self::Preparing->value, self::OutForDelivery->value, self::DeliveryFailed->value, self::CancellationRequested->value],
            'completed' => [self::Delivered->value, self::Received->value],
            'cancelled' => [self::Cancelled->value],
        ];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Allowed next statuses. The lifecycle only moves forward; the one
     * backward step is an admin rejecting a customer's cancellation request.
     * Cancelling is allowed up to delivery; received (customer-confirmed) is final.
     *
     * @return self[]
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::CancellationRequested, self::Cancelled],
            self::Confirmed => [self::Preparing, self::OutForDelivery, self::Cancelled],
            self::Preparing => [self::OutForDelivery, self::Cancelled],
            self::OutForDelivery => [self::Delivered, self::DeliveryFailed, self::Cancelled],
            // Nobody home, wrong address: the goods are still on the van. Try again, or give up.
            self::DeliveryFailed => [self::OutForDelivery, self::Cancelled],
            // Cancelling a delivered order is a return: stock and wallet are refunded.
            self::Delivered => [self::Received, self::Cancelled],
            self::CancellationRequested => [self::Cancelled, self::Pending],
            self::Received, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    /** @return string[] */
    public function nextValues(): array
    {
        return array_map(fn (self $status) => $status->value, $this->transitions());
    }
}
