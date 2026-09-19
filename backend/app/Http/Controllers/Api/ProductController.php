<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProductRequest;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
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
        // This route is open. The dashboard gets stock per warehouse; anyone
        // else gets only how many can be ordered, not where the company keeps them.
        if (ProductVariant::viewerSeesCost()) {
            return $this->jsonResponse($product->load([
                'category', 'allImages', 'images',
                'variants.images', 'variants.inventories.warehouse',
            ]));
        }

        $product->load(['category', 'images', 'variants' => fn ($q) => $q->where('is_active', true), 'variants.images']);
        $product->variants->loadSum('inventories as in_stock', 'quantity');
        $product->variants->each(fn ($v) => $v->setAttribute('in_stock', (int) $v->in_stock));

        return $this->jsonResponse($product);
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
        if (Inventory::whereIn('product_variant_id', $product->variants()->select('id'))->where('quantity', '>', 0)->exists()) {
            return $this->jsonResponse(['message' => 'لا يمكن حذف منتج ما زال له مخزون. صفّر مخزونه أولاً أو عطّله بدل حذفه.'], 422);
        }

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
