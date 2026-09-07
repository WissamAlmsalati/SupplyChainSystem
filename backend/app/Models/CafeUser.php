<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CafeUser extends Model
{
    use HasFactory, \App\Traits\LogsActivity;

    protected $table = 'cafe_user';

    protected $fillable = [
        'cafe_id',
        'user_id',
        'latitude',
        'longitude',
        'is_available',
        'location_updated_at',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'location_updated_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }
}
