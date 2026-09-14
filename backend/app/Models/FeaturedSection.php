<?php

namespace App\Models;

use App\Enums\FeaturedSectionSource;
use App\Services\ProductSearch;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FeaturedSection extends Model
{
    use LogsActivity;

    // Filter keys a rule-based section may store (same meaning as /cafe/products).
    public const FILTER_KEYS = ['category_id', 'brand', 'min_price', 'max_price', 'in_stock'];

    protected $fillable = [
        'title',
        'source',
        'sort',
        'filters',
        'products_limit',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'source' => FeaturedSectionSource::class,
        'filters' => 'json:unicode',
        'products_limit' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'source' => 'manual',
        'products_limit' => 10,
    ];

    /**
     * Products this section shows, in display order, with the card columns
     * (prices, stock, sales) that ProductSearch computes.
     */
    public function productsQuery(?int $userId = null): Builder
    {
        if ($this->source === FeaturedSectionSource::Filter) {
            $params = array_intersect_key($this->filters ?? [], array_flip(self::FILTER_KEYS)) + ['sort' => $this->sort ?: 'popular'];

            return (new ProductSearch(Request::create('/', 'GET', $params), $userId))->results();
        }

        return (new ProductSearch(Request::create('/', 'GET'), $userId))->results()
            ->join('featured_section_product as fsp', 'fsp.product_id', '=', 'products.id')
            ->where('fsp.featured_section_id', $this->id)
            ->reorder('fsp.sort_order')
            ->orderBy('products.id');
    }

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
