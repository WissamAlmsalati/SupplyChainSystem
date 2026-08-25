<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryZone extends Model
{
    use HasFactory, \App\Traits\LogsActivity;

    protected $table = 'delivery_zone';

    public $timestamps = true;

    protected $fillable = [
        'warehouse_id',
        'hex_id',
        'name',
        'delivery_price',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'delivery_price' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cafeBranches(): HasMany
    {
        return $this->hasMany(CafeBranch::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
