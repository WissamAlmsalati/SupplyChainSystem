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

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
