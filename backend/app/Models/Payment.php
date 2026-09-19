<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Payment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'order_id',
        'amount',
        'method',
        'status',
        'paid_at',
        'collected_by',
    ];

    protected static function booted(): void
    {
        // A payment can never exceed what is still owed on its order, whoever
        // records it (dashboard, delegate delivery, wallet checkout), and a
        // cancelled order takes no payments at all. Amounts compare in cents.
        static::saving(function (Payment $payment) {
            $status = $payment->status instanceof PaymentStatus ? $payment->status : PaymentStatus::from($payment->status);
            if (! in_array($status, [PaymentStatus::Paid, PaymentStatus::Pending], true)) {
                return;
            }
            if (! $payment->isDirty(['amount', 'status', 'order_id'])) {
                return;
            }

            $order = Order::find($payment->order_id);
            if (! $order) {
                return;
            }
            if ($order->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages(['order_id' => 'لا يمكن تسجيل دفعة على طلب ملغي']);
            }

            $cents = fn ($v) => (int) round((float) $v * 100);
            // Returns lower what the order owes, so they count here too.
            $outstanding = $order->balanceCents($payment->exists ? $payment->id : null)['outstanding'];

            if ($cents($payment->amount) <= 0) {
                throw ValidationException::withMessages(['amount' => 'المبلغ لازم يكون أكبر من صفر']);
            }
            if ($cents($payment->amount) > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'المبلغ يتجاوز المتبقي على الطلب ('.number_format($outstanding / 100, 2).' د.ل)',
                ]);
            }
        });
    }

    protected $casts = [
        'amount' => 'decimal:2',
        'method' => PaymentMethod::class,
        'status' => PaymentStatus::class,
        'paid_at' => 'datetime',
    ];

    public function collector(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'collected_by');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
