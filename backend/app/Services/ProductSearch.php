<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ArabicText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

// Customer catalog search: text query, facet filters, sorting and facet counts.
class ProductSearch
{
    public const SORTS = [
        'relevance' => 'الأقرب للبحث',
        'newest' => 'الأحدث',
        'price_asc' => 'السعر: من الأقل',
        'price_desc' => 'السعر: من الأعلى',
        'name_asc' => 'الاسم',
        'popular' => 'الأكثر مبيعاً',
    ];

    public function __construct(private Request $request, private ?int $userId = null) {}

    public function term(): string
    {
        return trim((string) ($this->request->input('q') ?? $this->request->input('search', '')));
    }

    public function sort(): string
    {
        $sort = (string) $this->request->input('sort');

        if (! array_key_exists($sort, self::SORTS) || ($sort === 'relevance' && $this->term() === '')) {
            return $this->term() !== '' ? 'relevance' : 'newest';
        }

        return $sort;
    }

    /**
     * Matching products; facets pass the filter they describe in $skip so their
     * own options are counted as if that filter were not applied.
     */
    public function query(array $skip = []): Builder
    {
        $query = Product::query()
            ->where('products.is_active', true)
            ->whereHas('variants', fn ($v) => $v->where('is_active', true));

        if (($term = $this->term()) !== '') {
            // Both sides are folded (أ/ا, ة/ه, harakat, Arabic-Indic digits), so
            // "قهوه" finds "قهوة" and "احمد" finds "أحمد".
            $like = '%'.addcslashes(ArabicText::normalize($term), '%_\\').'%';
            $matches = fn (string $column) => ArabicText::sqlExpression($column).' LIKE ?';

            $query->where(fn ($q) => $q
                ->whereRaw($matches('products.name'), [$like])
                ->orWhereRaw($matches('products.brand'), [$like])
                ->orWhereRaw($matches('products.description'), [$like])
                ->orWhereRaw($matches('products.tags'), [$like])
                ->orWhereHas('category', fn ($c) => $c->whereRaw($matches('categories.name'), [$like]))
                ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->where(fn ($vv) => $vv
                    ->whereRaw($matches('product_variants.name'), [$like])
                    ->orWhereRaw($matches('product_variants.sku'), [$like])
                    ->orWhereRaw($matches('product_variants.barcode'), [$like]))));
        }

        if (! in_array('category', $skip, true) && ($categoryIds = $this->ids('category_id'))) {
            $query->whereIn('products.category_id', $this->withDescendants($categoryIds));
        }

        if (! in_array('brand', $skip, true) && ($brands = $this->list('brand'))) {
            $query->whereIn('products.brand', $brands);
        }

        if (! in_array('price', $skip, true)) {
            $min = $this->request->input('min_price');
            $max = $this->request->input('max_price');
            if (is_numeric($min) || is_numeric($max)) {
                $query->whereHas('variants', fn ($v) => $v->where('is_active', true)
                    ->when(is_numeric($min), fn ($vv) => $vv->where('price', '>=', (float) $min))
                    ->when(is_numeric($max), fn ($vv) => $vv->where('price', '<=', (float) $max)));
            }
        }

        if (! in_array('in_stock', $skip, true) && $this->request->boolean('in_stock')) {
            $query->whereHas('variants', fn ($v) => $v->where('is_active', true)
                ->whereHas('inventories', fn ($i) => $i->where('quantity', '>', 0)));
        }

        if ($this->request->boolean('favorites') && $this->userId) {
            $query->whereIn('products.id', fn ($f) => $f->select('product_id')->from('favorites')->where('user_id', $this->userId));
        }

        return $query;
    }

    // query() plus the computed columns the cards and sorting need.
    public function results(): Builder
    {
        $activeVariants = fn () => ProductVariant::query()->whereColumn('product_variants.product_id', 'products.id')
            ->where('product_variants.is_active', true)->whereNull('product_variants.deleted_at');

        $query = $this->query()
            ->select('products.*')
            ->addSelect([
                'min_price_value' => $activeVariants()->selectRaw('MIN(price)'),
                'max_price_value' => $activeVariants()->selectRaw('MAX(price)'),
                'stock_quantity' => Inventory::query()->selectRaw('COALESCE(SUM(inventories.quantity), 0)')
                    ->join('product_variants', 'product_variants.id', '=', 'inventories.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('product_variants.is_active', true)->whereNull('product_variants.deleted_at'),
                'sold_quantity' => OrderItem::query()->selectRaw('COALESCE(SUM(order_items.quantity), 0)')
                    ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                    ->whereColumn('product_variants.product_id', 'products.id'),
            ])
            ->with(['allImages', 'variants' => fn ($v) => $v->where('is_active', true)->orderBy('id')]);

        $term = $this->term();

        match ($this->sort()) {
            'relevance' => $query
                ->orderByRaw(
                    'CASE WHEN '.ArabicText::sqlExpression('products.name').' = ? THEN 0'
                    .' WHEN '.ArabicText::sqlExpression('products.name').' LIKE ? THEN 1'
                    .' WHEN '.ArabicText::sqlExpression('products.name').' LIKE ? THEN 2 ELSE 3 END',
                    [
                        ArabicText::normalize($term),
                        addcslashes(ArabicText::normalize($term), '%_\\').'%',
                        '%'.addcslashes(ArabicText::normalize($term), '%_\\').'%',
                    ]
                )
                ->orderBy('products.name'),
            'price_asc' => $query->orderBy('min_price_value')->orderBy('products.id'),
            'price_desc' => $query->orderByDesc('min_price_value')->orderBy('products.id'),
            'name_asc' => $query->orderBy('products.name')->orderBy('products.id'),
            'popular' => $query->orderByDesc('sold_quantity')->orderBy('products.name'),
            default => $query->orderByDesc('products.created_at')->orderByDesc('products.id'),
        };

        return $query;
    }

    // The variant whose name, SKU or barcode matched the query, if any.
    public function matchedVariant(Product $product): ?ProductVariant
    {
        $term = $this->term();
        if ($term === '') {
            return null;
        }

        $needle = ArabicText::normalize($term);

        return $product->variants->first(fn (ProductVariant $v) => collect([$v->name, $v->sku, $v->barcode])
            ->contains(fn ($value) => $value !== null && str_contains(ArabicText::normalize($value), $needle)));
    }

    /**
     * Options for the filter screen, counted against the current query.
     */
    public function facets(): array
    {
        $categoryCounts = $this->query(['category'])
            ->reorder()->select('products.category_id')->selectRaw('COUNT(*) as aggregate')
            ->groupBy('products.category_id')->pluck('aggregate', 'category_id');

        $categories = Category::orderBy('name')->get(['id', 'name', 'parent_category_id'])
            ->map(function (Category $category) use ($categoryCounts) {
                $ids = $this->withDescendants([$category->id]);

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'parent_category_id' => $category->parent_category_id,
                    'count' => (int) collect($ids)->sum(fn ($id) => $categoryCounts[$id] ?? 0),
                ];
            })
            ->filter(fn ($c) => $c['count'] > 0)
            ->values();

        $brands = $this->query(['brand'])
            ->reorder()->whereNotNull('products.brand')->where('products.brand', '!=', '')
            ->select('products.brand')->selectRaw('COUNT(*) as aggregate')
            ->groupBy('products.brand')->orderBy('products.brand')
            ->get()->map(fn ($row) => ['name' => $row->brand, 'count' => (int) $row->aggregate]);

        $prices = ProductVariant::query()
            ->where('is_active', true)
            ->whereIn('product_id', $this->query(['price'])->reorder()->select('products.id'))
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        return [
            'total' => $this->query()->count(),
            'in_stock_count' => $this->query(['in_stock'])->whereHas('variants', fn ($v) => $v->where('is_active', true)
                ->whereHas('inventories', fn ($i) => $i->where('quantity', '>', 0)))->count(),
            'categories' => $categories,
            'brands' => $brands,
            'price' => [
                'min' => $prices?->min_price !== null ? round((float) $prices->min_price, 2) : null,
                'max' => $prices?->max_price !== null ? round((float) $prices->max_price, 2) : null,
            ],
            'sort_options' => collect(self::SORTS)
                ->reject(fn ($label, $value) => $value === 'relevance' && $this->term() === '')
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'applied' => $this->applied(),
        ];
    }

    public function applied(): array
    {
        return array_filter([
            'q' => $this->term() ?: null,
            'category_id' => $this->ids('category_id') ?: null,
            'brand' => $this->list('brand') ?: null,
            'min_price' => is_numeric($this->request->input('min_price')) ? (float) $this->request->input('min_price') : null,
            'max_price' => is_numeric($this->request->input('max_price')) ? (float) $this->request->input('max_price') : null,
            'in_stock' => $this->request->boolean('in_stock') ?: null,
            'favorites' => $this->request->boolean('favorites') ?: null,
            'sort' => $this->sort(),
        ], fn ($v) => $v !== null);
    }

    // Accepts "1,2" or category_id[]=1&category_id[]=2.
    private function list(string $key): array
    {
        $value = $this->request->input($key);
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('trim', $items), fn ($v) => $v !== ''));
    }

    private function ids(string $key): array
    {
        return array_values(array_unique(array_map('intval', array_filter($this->list($key), 'is_numeric'))));
    }

    private function withDescendants(array $ids): array
    {
        $all = collect($ids);
        $frontier = $ids;
        while ($frontier) {
            $children = Category::whereIn('parent_category_id', $frontier)->pluck('id')->diff($all)->all();
            $all = $all->merge($children);
            $frontier = $children;
        }

        return $all->unique()->values()->all();
    }
}
