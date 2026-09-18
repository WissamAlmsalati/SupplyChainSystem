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

    protected $fillable = ['order_id', 'reason', 'total_value', 'refund_amount', 'refund_method', 'created_by'];

    protected $casts = [
        'total_value' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

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
