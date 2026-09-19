<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Written only by ReturnService. Rows are a record of what happened and are
// never edited: a mistake is corrected with a stock or wallet adjustment that
// leaves its own trace.
class OrderReturn extends Model
{
    public const REFUND_METHODS = ['wallet', 'cash', 'none'];

    public const REFUND_LABELS = [
        'wallet' => 'إلى المحفظة',
        'cash' => 'نقداً',
        'none' => 'بدون استرداد',
    ];

    protected $fillable = ['order_id', 'reason', 'total_value', 'refund_amount', 'refund_method', 'refund_paid_at', 'refund_paid_by', 'refund_paid_from_delegate_id', 'created_by'];

    protected $casts = [
        'total_value' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refund_paid_at' => 'datetime',
    ];

    protected $appends = ['refund_pending'];

    // Money is owed and nobody has handed it over yet.
    public function getRefundPendingAttribute(): bool
    {
        return (float) $this->refund_amount > 0 && $this->refund_paid_at === null;
    }

    public function refundPaidBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'refund_paid_by');
    }

    public function refundPaidFromDelegate(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'refund_paid_from_delegate_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'created_by');
    }
}
