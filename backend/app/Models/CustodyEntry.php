<?php

namespace App\Models;

use App\Enums\CustodyEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustodyEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'delegate_id',
        'type',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'note',
        'created_by',
    ];

    protected $casts = [
        'type' => CustodyEntryType::class,
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'delegate_id')->withTrashed();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'created_by');
    }
}
