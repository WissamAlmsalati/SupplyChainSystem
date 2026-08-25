<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, \App\Traits\LogsActivity;

    protected $table = 'order';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'branch_id',
        'delegate_id',
        'delivery_zone_id',
        'delivery_fee',
        'order_date',
        'status',
        'source',
        'total_amount',
    ];

    protected $casts = [
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'order_date' => 'timestamp',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'delegate_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CafeBranch::class, 'branch_id');
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
        return $this->hasMany(OrderStatusLog::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
