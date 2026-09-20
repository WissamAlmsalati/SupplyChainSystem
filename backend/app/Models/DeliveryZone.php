<?php

namespace App\Models;

use App\Services\AddressZoneResolver;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryZone extends Model
{
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        // A zone that starts delivering (new, switched on, or moved) takes in the
        // addresses that were waiting for it. Written as a block: a listener that
        // returns false would stop the ones after it. Never let it fail the save.
        static::saved(function (DeliveryZone $zone) {
            if ($zone->is_active && ($zone->wasRecentlyCreated || $zone->wasChanged(['is_active', 'hex_id']))) {
                rescue(fn () => app(AddressZoneResolver::class)->adopt($zone));
            }
        });
    }

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

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
