<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CafeBranch extends Model
{
    use HasFactory, \App\Traits\LogsActivity;

    protected $table = 'cafe_branch';

    public $timestamps = false;

    protected $fillable = [
        'cafe_id',
        'name',
        'city',
        'street',
        'latitude',
        'longitude',
        'delivery_zone_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:6',
        'longitude' => 'decimal:6',
    ];

    public function cafe(): BelongsTo
    {
        return $this->belongsTo(Cafe::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'branch_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'branch_id');
    }
}
