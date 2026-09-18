<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'allImages'])->withCount('variants');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->integer('per_page', 15);

        return $this->paginated($query->orderByDesc('id')->paginate($perPage > 0 ? min($perPage, 10000) : 15));
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['image']);

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);
            if ($request->hasFile('image')) {
                $this->replacePrimaryImage($product, $request->file('image')->store('products', 'public'));
            }

            return $product;
        });

        return $this->jsonResponse($product->load(['category', 'allImages']), 201);
    }

    public function show(Product $product): JsonResponse
    {
        return $this->jsonResponse($product->load([
            'category', 'allImages', 'images',
            'variants.images', 'variants.inventories.warehouse',
        ]));
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        unset($data['image']);

        DB::transaction(function () use ($product, $data, $request) {
            $product->update($data);
            if ($request->hasFile('image')) {
                $this->replacePrimaryImage($product, $request->file('image')->store('products', 'public'));
            }
        });

        return $this->jsonResponse($product->load(['category', 'allImages']));
    }

    // Soft delete: the product stays referenced by past orders.
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return $this->jsonResponse(null, 204);
    }

    private function replacePrimaryImage(Product $product, string $path): void
    {
        $old = $product->images()->where('is_primary', true)->first();
        if ($old) {
            if ($old->isStored()) {
                Storage::disk('public')->delete($old->path);
            }
            $old->delete();
        }

        ProductImage::create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'path' => $path,
            'is_primary' => true,
        ]);

        $product->unsetRelation('allImages');
    }
}
