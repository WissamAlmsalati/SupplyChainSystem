<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockMovementType;
use App\Http\Requests\Api\InventoryRequest;
use App\Models\Inventory;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Stock balances. Every change goes through StockService and is recorded in stock_movements.
class InventoryController extends BaseApiController
{
    public function __construct(private StockService $stock) {}

    public function index(Request $request): JsonResponse
    {
        $query = Inventory::with(['warehouse', 'productVariant.product']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('productVariant.product', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('productVariant', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('warehouse', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->integer('product_variant_id'));
        }

        $perPage = $request->integer('per_page', 15);

        return $this->jsonResponse($query->orderByDesc('id')->paginate($perPage > 0 ? min($perPage, 10000) : 15));
    }

    // Adds stock to a warehouse (sums with any existing balance).
    public function store(InventoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $inventory = $this->stock->adjust(
            $data['warehouse_id'],
            $data['product_variant_id'],
            (int) $data['quantity'],
            StockMovementType::Adjustment,
            null,
            $data['note'] ?? 'إضافة مخزون يدوية',
        );

        return $this->jsonResponse($inventory->load(['warehouse', 'productVariant.product']), 201);
    }

    public function show(Inventory $inventory): JsonResponse
    {
        return $this->jsonResponse($inventory->load(['warehouse', 'productVariant.product']));
    }

    // Sets the counted on-hand quantity; the difference is recorded as an adjustment.
    public function update(InventoryRequest $request, Inventory $inventory): JsonResponse
    {
        $data = $request->validated();

        $inventory = $this->stock->setQuantity($inventory, (int) $data['quantity'], $data['note'] ?? 'جرد يدوي');

        return $this->jsonResponse($inventory->load(['warehouse', 'productVariant.product']));
    }

    public function destroy(Inventory $inventory): JsonResponse
    {
        if ($inventory->quantity > 0) {
            return $this->jsonResponse(['message' => 'لا يمكن حذف سجل مخزون به كمية، قم بتصفيره أولاً'], 422);
        }

        $inventory->delete();

        return $this->jsonResponse(null, 204);
    }
}
