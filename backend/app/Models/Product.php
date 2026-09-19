<?php

namespace App\Models;

use App\Support\Placeholder;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'brand',
        'description',
        'tags',
        'is_active',
    ];

    protected $casts = [
        // Stored unescaped so Arabic tags are searchable with LIKE.
        'tags' => 'json:unicode',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
        'image_type',
        'image_is_placeholder',
    ];

    // Primary product-level image, falling back to the first variant image.
    public function getImageUrlAttribute(): ?string
    {
        $images = $this->relationLoaded('allImages')
            ? $this->allImages
            : $this->allImages()->get();

        $own = $images->whereNull('product_variant_id');
        $image = $own->firstWhere('is_primary', true) ?? $own->first()
            ?? $images->firstWhere('is_primary', true) ?? $images->first();

        return $image?->image_url ?? Placeholder::url('product');
    }

    // The file format of image_url (svg, jpg, png...), so a client knows how to draw it.
    public function getImageTypeAttribute(): ?string
    {
        return Placeholder::typeFor($this->image_url);
    }

    // True while the picture is the shared default artwork, not one somebody uploaded.
    public function getImageIsPlaceholderAttribute(): bool
    {
        return Placeholder::isPlaceholder($this->image_url);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    // Images of the product itself (not of a specific variant).
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->whereNull('product_variant_id')->orderBy('sort_order')->orderBy('id');
    }

    public function allImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }
}
