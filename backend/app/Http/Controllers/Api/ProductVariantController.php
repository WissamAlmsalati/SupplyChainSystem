<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProductVariantRequest;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductVariant::with('product');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhereHas('product', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = $request->integer('per_page', 15);

        return $this->jsonResponse($query->orderByDesc('id')->paginate($perPage > 0 ? min($perPage, 10000) : 15));
    }

    // ponytail: SKU is generated English/numeric from product+id — the id-based
    // fallback also self-heals any client-supplied garbage (e.g. Arabic values).
    // Zero-padded (PRD-0003-00028 / PRD-0003-250ML) for fixed-length, sortable SKUs.
    private function ensureSku(ProductVariant $variant): void
    {
        if ($variant->sku && preg_match('/^[A-Za-z0-9-_]+$/', $variant->sku)) {
            return;
        }

        $ascii = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', trim((string) $variant->name)));
        $ascii = trim($ascii, '-');
        $prefix = 'PRD-' . str_pad((string) $variant->product_id, 4, '0', STR_PAD_LEFT) . '-';
        $sku = $prefix . ($ascii ?: str_pad((string) $variant->id, 5, '0', STR_PAD_LEFT));

        if (ProductVariant::withTrashed()->where('sku', $sku)->whereKeyNot($variant->id)->exists()) {
            $sku = $prefix . str_pad((string) $variant->id, 5, '0', STR_PAD_LEFT);
        }

        $variant->update(['sku' => $sku]);
    }

    public function store(ProductVariantRequest $request): JsonResponse
    {
        $variant = ProductVariant::create($request->validated());
        $this->ensureSku($variant);

        return $this->jsonResponse($variant->load('product'), 201);
    }

    public function show(ProductVariant $productVariant): JsonResponse
    {
        return $this->jsonResponse($productVariant->load(['product', 'inventories.warehouse', 'images']));
    }

    public function update(ProductVariantRequest $request, ProductVariant $productVariant): JsonResponse
    {
        $productVariant->update($request->validated());
        $this->ensureSku($productVariant);

        return $this->jsonResponse($productVariant->load('product'));
    }

    // Soft delete: the variant stays referenced by past orders and stock movements.
    public function destroy(ProductVariant $productVariant): JsonResponse
    {
        $productVariant->delete();

        return $this->jsonResponse(null, 204);
    }
}
