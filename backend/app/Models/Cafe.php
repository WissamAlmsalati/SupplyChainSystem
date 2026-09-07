<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cafe extends Model
{
    use HasFactory, \App\Traits\LogsActivity;

    protected $table = 'cafe';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'contact_info',
        'address',
        'latitude',
        'longitude',
        'image',
        'created_by_admin_id',
        'is_active',
    ];

    protected $appends = [
        'image_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'timestamp',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? '/storage/' . ltrim($this->image, '/') : null;
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'created_by_admin_id');
    }

    public function cafeUsers(): HasMany
    {
        return $this->hasMany(CafeUser::class);
    }

    public function appUsers(): BelongsToMany
    {
        return $this->belongsToMany(AppUser::class, 'cafe_user', 'cafe_id', 'user_id')
            ->withPivot(['latitude', 'longitude', 'is_available', 'location_updated_at']);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(CafeBranch::class);
    }
}
