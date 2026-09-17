<?php

namespace App\Http\Controllers\Api;

use App\Enums\FeaturedSectionSource;
use App\Http\Requests\Api\FeaturedSectionRequest;
use App\Models\FeaturedSection;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Admin: curated product sections for the customer app (hand-picked or rule-based).
class FeaturedSectionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = FeaturedSection::withCount('products')->orderBy('sort_order')->orderBy('id');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->input('search') . '%');
        }

        return $this->paginated($query->paginate($request->integer('per_page', 50)));
    }

    public function store(FeaturedSectionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $section = DB::transaction(function () use ($data) {
            $section = FeaturedSection::create($this->attributes($data) + [
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? (int) FeaturedSection::max('sort_order') + 1,
            ]);
            $this->syncSource($section, $data);

            return $section;
        });

        // Same flat shape as show/update (jsonResponse would wrap a 201 array under "data").
        return response()->json($this->withProducts($section), 201, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(FeaturedSection $featuredSection): JsonResponse
    {
        return $this->jsonResponse($this->withProducts($featuredSection));
    }

    public function update(FeaturedSectionRequest $request, FeaturedSection $featuredSection): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($featuredSection, $data) {
            $featuredSection->update($this->attributes($data) + collect($data)->only(['is_active', 'sort_order'])->all());
            $this->syncSource($featuredSection, $data);
        });

        return $this->jsonResponse($this->withProducts($featuredSection->fresh()));
    }

    public function destroy(FeaturedSection $featuredSection): JsonResponse
    {
        $featuredSection->delete();

        return $this->jsonResponse(null, 204);
    }

    // Saves the order of sections as given (first id = shown first).
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:featured_sections,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $index => $id) {
                FeaturedSection::whereKey($id)->update(['sort_order' => $index]);
            }
        });

        return $this->jsonResponse(FeaturedSection::withCount('products')->orderBy('sort_order')->get());
    }

    // Products a rule would return right now, without saving the section.
    public function preview(FeaturedSectionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $section = new FeaturedSection($this->attributes($data + ['source' => FeaturedSectionSource::Filter->value]));
        $query = $section->productsQuery();
        $limit = $section->products_limit ?: 10;

        return $this->jsonResponse([
            'total' => (clone $query)->reorder()->count(),
            'products' => $query->limit($limit)->get()->map(fn (Product $p) => $this->adminCard($p)),
        ]);
    }

    private function attributes(array $data): array
    {
        $attributes = collect($data)->only(['title', 'source', 'sort', 'products_limit'])->all();

        if (array_key_exists('filters', $data)) {
            $attributes['filters'] = collect($data['filters'] ?? [])
                ->only(FeaturedSection::FILTER_KEYS)
                ->reject(fn ($v) => $v === null || $v === '' || $v === [] || $v === false)
                ->all() ?: null;
        }

        return $attributes;
    }

    // Manual sections keep their picked products; rule-based ones do not use the pivot.
    private function syncSource(FeaturedSection $section, array $data): void
    {
        if ($section->source === FeaturedSectionSource::Filter) {
            $section->products()->detach();
            $section->update(['sort' => $section->sort ?: 'popular']);
        } elseif (array_key_exists('product_ids', $data)) {
            $section->syncProducts($data['product_ids']);
        }
    }

    private function withProducts(FeaturedSection $section): array
    {
        if ($section->source === FeaturedSectionSource::Filter) {
            $query = $section->productsQuery();

            return $section->toArray() + [
                'products_total' => (clone $query)->reorder()->count(),
                'products' => $query->limit($section->products_limit)->get()->map(fn (Product $p) => $this->adminCard($p)),
            ];
        }

        $section->load(['products' => fn ($q) => $q->withTrashed()->with(['allImages', 'category:id,name'])->withMin('variants', 'price')]);

        return $section->toArray() + ['products_total' => $section->products->count()];
    }

    private function adminCard(Product $product): array
    {
        return CafeMobileController::productCard($product) + [
            'sold_quantity' => (int) $product->getAttribute('sold_quantity'),
            'category' => $product->category?->only(['id', 'name']),
        ];
    }
}
