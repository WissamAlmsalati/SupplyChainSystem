<?php

namespace App\Models;

use App\Support\Placeholder;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'image',
        'parent_category_id',
    ];

    protected $appends = [
        'image_url',
        'image_type',
        'image_is_placeholder',
    ];

    // Categories fall back to the shared default artwork when no picture was uploaded.
    public function getImageUrlAttribute(): string
    {
        return $this->image ? '/storage/'.ltrim($this->image, '/') : Placeholder::url('category');
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

    public function parentCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    public function childCategories(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_category_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
