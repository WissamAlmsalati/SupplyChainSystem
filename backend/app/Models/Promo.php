<?php

namespace App\Models;

use App\Support\Placeholder;
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

    protected $appends = ['image_url', 'image_type', 'image_is_placeholder'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? '/storage/'.ltrim($this->image, '/') : Placeholder::url('promo');
    }

    public function getImageTypeAttribute(): ?string
    {
        return Placeholder::typeFor($this->image_url);
    }

    // True while the picture is the shared default artwork, not one somebody uploaded.
    public function getImageIsPlaceholderAttribute(): bool
    {
        return Placeholder::isPlaceholder($this->image_url);
    }
}
