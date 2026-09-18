<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnItem extends Model
{
    public const CONDITIONS = ['restock', 'damaged'];

    public const CONDITION_LABELS = [
        'restock' => 'أُعيد للمخزون',
        'damaged' => 'تالف',
    ];

    public $timestamps = false;

    protected $fillable = ['order_return_id', 'order_item_id', 'quantity', 'unit_price', 'condition', 'warehouse_id'];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
