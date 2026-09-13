<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelegateProfile extends Model
{
    protected $fillable = [
        'user_id',
        'is_available',
        'latitude',
        'longitude',
        'location_updated_at',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'location_updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }
}
