<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\FeaturedSectionRequest;
use App\Models\FeaturedSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Admin: curated product sections for the customer app.
class FeaturedSectionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = FeaturedSection::withCount('products')->orderBy('sort_order')->orderBy('id');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->input('search') . '%');
        }

        return $this->jsonResponse($query->paginate($request->integer('per_page', 50)));
    }

    public function store(FeaturedSectionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $section = DB::transaction(function () use ($data) {
            $section = FeaturedSection::create([
                'title' => $data['title'],
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? (int) FeaturedSection::max('sort_order') + 1,
            ]);
            $section->syncProducts($data['product_ids']);

            return $section;
        });

        return $this->jsonResponse($this->withProducts($section), 201);
    }

    public function show(FeaturedSection $featuredSection): JsonResponse
    {
        return $this->jsonResponse($this->withProducts($featuredSection));
    }

    public function update(FeaturedSectionRequest $request, FeaturedSection $featuredSection): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($featuredSection, $data) {
            $featuredSection->update(collect($data)->only(['title', 'is_active', 'sort_order'])->all());
            if (array_key_exists('product_ids', $data)) {
                $featuredSection->syncProducts($data['product_ids']);
            }
        });

        return $this->jsonResponse($this->withProducts($featuredSection));
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

    private function withProducts(FeaturedSection $section): FeaturedSection
    {
        return $section->load(['products' => fn ($q) => $q->withTrashed()->with(['allImages', 'category:id,name'])->withMin('variants', 'price')]);
    }
}
