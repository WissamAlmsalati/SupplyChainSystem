<?php

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Services\CustodyService;
use App\Services\StockService;
use App\Services\WalletService;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'order_number',
        'user_id',
        'delegate_id',
        'cart_id',
        'status',
        'source',
        'address_id',
        'delivery_address_name',
        'delivery_city',
        'delivery_street',
        'delivery_phones',
        'delivery_latitude',
        'delivery_longitude',
        'delivery_zone_id',
        'subtotal',
        'delivery_fee',
        'total_amount',
        'placed_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'source' => OrderSource::class,
        'delivery_phones' => 'array',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'placed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->order_number ??= self::generateOrderNumber();
            $order->status ??= OrderStatus::Pending;
            $order->placed_at ??= now();
        });

        // Every status change is written to order_status_logs.
        static::created(fn (Order $order) => $order->logStatus(null));
        static::updated(function (Order $order) {
            if (! $order->wasChanged('status')) {
                return;
            }
            $order->logStatus($order->getOriginal('status'));
            // Delivering a cash order means its delegate collected the unpaid amount.
            if ($order->status === OrderStatus::Delivered) {
                app(CustodyService::class)->collectOrderCash($order);
            }
            // Cancelling returns whatever this order took from stock.
            if ($order->status === OrderStatus::Cancelled) {
                app(StockService::class)->restockOrder($order);
                app(WalletService::class)->refundOrder($order);
            }
        });
    }

    protected function logStatus(OrderStatus|string|null $from): void
    {
        $this->statusLogs()->create([
            'from_status' => $from instanceof OrderStatus ? $from->value : $from,
            'to_status' => $this->status->value,
            'changed_by' => auth()->id(),
        ]);
    }

    // Copies the address into the order's delivery snapshot fields.
    public function fillDeliveryAddress(Address $address): static
    {
        return $this->fill([
            'address_id' => $address->id,
            'delivery_address_name' => $address->name,
            'delivery_city' => $address->city,
            'delivery_street' => $address->street,
            'delivery_phones' => $address->contact_phones,
            'delivery_latitude' => $address->latitude,
            'delivery_longitude' => $address->longitude,
            'delivery_zone_id' => $address->delivery_zone_id,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'delegate_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class)->withTrashed();
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD-' . date('Y') . '-';
        $maxAttempts = 10;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $last = self::where('order_number', 'like', $prefix . '%')
                ->orderByDesc('order_number')
                ->value('order_number');

            $sequence = 1;
            if ($last) {
                $sequence = (int) substr($last, strlen($prefix)) + 1;
            }

            $number = $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);

            if (! self::where('order_number', $number)->exists()) {
                return $number;
            }
        }

        // Fallback with microtime if collisions persist under heavy concurrency
        return $prefix . str_pad((int) (microtime(true) * 1000), 10, '0', STR_PAD_LEFT);
    }
}
