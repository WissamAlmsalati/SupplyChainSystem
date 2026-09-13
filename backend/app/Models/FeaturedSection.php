<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FeaturedSection extends Model
{
    use LogsActivity;

    protected $fillable = [
        'title',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Products in the order the admin arranged them.
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    // Replaces the section's products, keeping the given order.
    public function syncProducts(array $productIds): void
    {
        $this->products()->sync(
            collect(array_values(array_unique($productIds)))
                ->mapWithKeys(fn ($id, $index) => [(int) $id => ['sort_order' => $index]])
                ->all()
        );
    }
}
