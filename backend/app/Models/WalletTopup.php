<?php

namespace App\Models;

use App\Enums\TopupMethod;
use App\Enums\TopupStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopup extends Model
{
    protected $fillable = [
        'wallet_id',
        'user_id',
        'amount',
        'method',
        'status',
        'reference_number',
        'receipt_path',
        'note',
        'collected_by',
        'order_id',
        'gateway_token',
        'gateway_reference',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'method' => TopupMethod::class,
        'status' => TopupStatus::class,
        'reviewed_at' => 'datetime',
    ];

    protected $hidden = [
        'gateway_token',
    ];

    protected $appends = [
        'receipt_url',
        'receipt_type',
    ];

    // "pdf" or "image", so clients know how to preview the receipt.
    public function getReceiptTypeAttribute(): ?string
    {
        if (! $this->receipt_path) {
            return null;
        }

        return str_ends_with(strtolower($this->receipt_path), '.pdf') ? 'pdf' : 'image';
    }

    public function getReceiptUrlAttribute(): ?string
    {
        return $this->receipt_path ? '/storage/' . ltrim($this->receipt_path, '/') : null;
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id')->withTrashed();
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'collected_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'reviewed_by');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
