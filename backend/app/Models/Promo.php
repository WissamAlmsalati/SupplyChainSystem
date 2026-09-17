<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promo extends Model
{
    use HasFactory;

    protected $fillable = ['image', 'description', 'link', 'show_description', 'is_active'];

    protected $casts = [
        'show_description' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['image_url', 'image_type'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? '/storage/' . ltrim($this->image, '/') : \App\Support\Placeholder::url('promo');
    }

    public function getImageTypeAttribute(): string
    {
        return \App\Support\Placeholder::typeFor($this->image_url);
    }
}
