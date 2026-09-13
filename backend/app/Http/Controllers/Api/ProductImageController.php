<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProductImageRequest;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Product images (product_variant_id null) and variant images share one table.
class ProductImageController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductImage::with(['product', 'productVariant']);

        foreach (['product_id', 'product_variant_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->integer($filter));
            }
        }

        return $this->jsonResponse($query->orderBy('sort_order')->orderByDesc('id')->paginate(15));
    }

    public function store(ProductImageRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['product_variant_id'])) {
            $data['product_id'] = ProductVariant::withTrashed()->findOrFail($data['product_variant_id'])->product_id;
        }

        $data['path'] = $request->hasFile('image')
            ? $request->file('image')->store('product-images', 'public')
            : $data['url'];
        unset($data['image'], $data['url']);

        $image = DB::transaction(function () use ($data) {
            $image = ProductImage::create($data);
            if ($image->is_primary) {
                $this->demoteOtherPrimaries($image);
            }

            return $image;
        });

        return $this->jsonResponse($image->load(['product', 'productVariant']), 201);
    }

    public function show(ProductImage $productImage): JsonResponse
    {
        return $this->jsonResponse($productImage->load(['product', 'productVariant']));
    }

    public function update(ProductImageRequest $request, ProductImage $productImage): JsonResponse
    {
        $data = collect($request->validated())->only(['is_primary', 'sort_order'])->all();

        if ($request->hasFile('image') || $request->filled('url')) {
            if ($productImage->isStored()) {
                Storage::disk('public')->delete($productImage->path);
            }
            $data['path'] = $request->hasFile('image')
                ? $request->file('image')->store('product-images', 'public')
                : $request->input('url');
        }

        DB::transaction(function () use ($productImage, $data) {
            $productImage->update($data);
            if ($productImage->is_primary) {
                $this->demoteOtherPrimaries($productImage);
            }
        });

        return $this->jsonResponse($productImage->load(['product', 'productVariant']));
    }

    public function destroy(ProductImage $productImage): JsonResponse
    {
        if ($productImage->isStored()) {
            Storage::disk('public')->delete($productImage->path);
        }

        $productImage->delete();

        return $this->jsonResponse(null, 204);
    }

    // One primary image per product (own images) or per variant.
    private function demoteOtherPrimaries(ProductImage $image): void
    {
        ProductImage::where('product_id', $image->product_id)
            ->where('product_variant_id', $image->product_variant_id)
            ->whereKeyNot($image->id)
            ->update(['is_primary' => false]);
    }
}
