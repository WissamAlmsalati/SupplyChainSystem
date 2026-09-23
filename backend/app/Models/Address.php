<?php

namespace App\Models;

use App\Models\Concerns\HasImages;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, HasImages, LogsActivity, SoftDeletes;

    // Which default artwork stands in when the cafe has photographed nothing.
    protected string $placeholderKind = 'customer';

    protected $appends = ['images'];

    protected $fillable = [
        'user_id',
        'name',
        'city',
        'street',
        'contact_phones',
        'latitude',
        'longitude',
        'delivery_zone_id',
    ];

    protected $casts = [
        'contact_phones' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
