<?php

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Services\CustodyService;
use App\Services\StockService;
use App\Services\WalletService;
use App\Support\BusinessTime;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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
            $order->placed_at ??= now();
            $order->order_number ??= self::generateOrderNumber($order->placed_at);
            $order->status ??= OrderStatus::Pending;
        });

        // Only the moves in OrderStatus::transitions() are allowed, whoever asks
        // (dashboard, delegate app, customer app): delivered never goes back to
        // pending, and cancelled/received are final.
        static::updating(function (Order $order) {
            if (! $order->isDirty('status')) {
                return;
            }
            $from = $order->getOriginal('status');
            $from = $from instanceof OrderStatus ? $from : OrderStatus::from($from);
            if (! $from->canTransitionTo($order->status)) {
                throw ValidationException::withMessages([
                    'status' => "لا يمكن نقل الطلب من «{$from->label()}» إلى «{$order->status->label()}»",
                ]);
            }
            // Cancelling restocks everything and refunds every payment. Once
            // part of the order has already come back through a return, that
            // would restock and refund the same goods twice.
            if ($order->status === OrderStatus::Cancelled && $order->returns()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'الطلب عليه مرتجع مسجّل، سجّل مرتجعاً بباقي الأصناف بدل الإلغاء',
                ]);
            }
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
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

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    /**
     * Where the order stands in money, in one place so the payment guard, the
     * returns service and the dashboard cannot disagree. Everything in cents.
     *
     * due = what was sold minus what came back; paid = payments minus what was
     * handed back for returns; outstanding = due - paid.
     *
     * @return array{total:int, returned:int, due:int, paid:int, refunded:int, outstanding:int}
     */
    public function balanceCents(?int $exceptPaymentId = null): array
    {
        $cents = fn ($v) => (int) round((float) $v * 100);

        $total = $cents($this->total_amount);
        $returned = $cents($this->returns()->sum('total_value'));
        $refunded = $cents($this->returns()->sum('refund_amount'));
        $paid = $cents($this->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->when($exceptPaymentId, fn ($q) => $q->where('id', '!=', $exceptPaymentId))
            ->sum('amount'));

        return [
            'total' => $total,
            'returned' => $returned,
            'due' => $total - $returned,
            'paid' => $paid,
            'refunded' => $refunded,
            'outstanding' => ($total - $returned) - ($paid - $refunded),
        ];
    }

    /**
     * ORD-YYYY-MM-DD-HH-NNN, counted within the hour it was placed. The number
     * says when the order came in, which is how the office talks about them
     * ("the 2pm ones"), and the counter starting again each hour keeps it short
     * and keeps two clerks from racing over the same next number all day.
     * Numbers issued under the old ORD-YYYY-NNNNN format are left alone.
     */
    public static function generateOrderNumber(?Carbon $at = null): string
    {
        // Shown to people in Libya, so the hour is theirs, not the server's UTC.
        $prefix = 'ORD-'.BusinessTime::format($at ?? now(), 'Y-m-d-H').'-';
        $maxAttempts = 10;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $last = self::where('order_number', 'like', $prefix.'%')
                ->orderByDesc('order_number')
                ->value('order_number');

            $sequence = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
            $number = $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

            if (! self::where('order_number', $number)->exists()) {
                return $number;
            }
        }

        // Two clerks on the same second in the same hour: fall back to something
        // unique rather than hand back a number that is already taken.
        return $prefix.substr((string) (int) (microtime(true) * 1000), -6);
    }
}
