<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductImage extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'path',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'image_url',
    ];

    // Whether the image lives on the public disk (vs an external URL).
    public function isStored(): bool
    {
        return ! Str::startsWith($this->path, ['http://', 'https://', '/']);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return $this->isStored() ? '/storage/' . $this->path : $this->path;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
