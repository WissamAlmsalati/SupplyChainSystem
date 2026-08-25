<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cafe extends Model
{
    use HasFactory, \App\Traits\LogsActivity;

    protected $table = 'cafe';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'contact_info',
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
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'created_by_admin_id');
    }

    public function appUsers(): HasMany
    {
        return $this->hasMany(AppUser::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(CafeBranch::class);
    }
}
