<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use LogsActivity;

    protected $fillable = [
        'reference_number',
        'warehouse_id',
        'status',
        'note',
        'created_by',
        'received_at',
    ];

    protected $casts = [
        'status' => PurchaseOrderStatus::class,
        'received_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $po) {
            $po->status ??= PurchaseOrderStatus::Draft;
            $po->created_by ??= auth()->id();
            $po->reference_number ??= self::generateReferenceNumber();
        });
    }

    public static function generateReferenceNumber(): string
    {
        $prefix = 'PO-' . date('Y') . '-';
        $last = self::where('reference_number', 'like', $prefix . '%')
            ->orderByDesc('reference_number')
            ->value('reference_number');

        $sequence = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
