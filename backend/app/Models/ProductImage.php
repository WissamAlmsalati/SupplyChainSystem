<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory, \App\Traits\LogsActivity;

    protected $table = 'product_image';

    public $timestamps = false;

    protected $fillable = [
        'product_variant_id',
        'url',
        'image',
        'is_primary',
    ];

    protected $appends = [
        'image_url',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image) {
            return '/storage/' . ltrim($this->image, '/');
        }

        return $this->url ?: null;
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
