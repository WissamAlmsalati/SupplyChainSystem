<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelegateSettlement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'reference_number',
        'delegate_id',
        'amount',
        'custody_before',
        'custody_after',
        'received_by',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'custody_before' => 'decimal:2',
        'custody_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DelegateSettlement $settlement) {
            $prefix = 'STL-' . date('Y') . '-';
            $last = self::where('reference_number', 'like', $prefix . '%')->orderByDesc('reference_number')->value('reference_number');
            $settlement->reference_number ??= $prefix . str_pad(($last ? (int) substr($last, strlen($prefix)) : 0) + 1, 5, '0', STR_PAD_LEFT);
        });
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'delegate_id')->withTrashed();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'received_by');
    }
}
