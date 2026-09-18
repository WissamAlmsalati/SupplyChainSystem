<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockMovementType;
use App\Http\Requests\Api\InventoryRequest;
use App\Models\Inventory;
use App\Models\StockMovement;
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

        return $this->paginated($query->orderByDesc('id')->paginate($perPage > 0 ? min($perPage, 10000) : 15));
    }

    // Goods received into a warehouse: sums with the balance and records a purchase
    // movement carrying the cost, manufacturing year and expiry date.
    public function store(InventoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Guard against a double click or a retried request repeating the same
        // goods-in: an identical movement seconds ago is almost certainly a dupe.
        $recent = StockMovement::where('warehouse_id', $data['warehouse_id'])
            ->where('product_variant_id', $data['product_variant_id'])
            ->where('quantity_change', (int) $data['quantity'])
            ->where('type', StockMovementType::Purchase)
            ->where('created_by', auth()->id())
            ->where('created_at', '>=', now()->subSeconds(10))
            ->exists();

        if ($recent) {
            return $this->jsonResponse([
                'message' => 'تم تسجيل نفس الإدخال قبل لحظات. راجع سجل الإدخال قبل إعادة المحاولة.',
            ], 422);
        }

        $inventory = $this->stock->receive(
            $data['warehouse_id'],
            $data['product_variant_id'],
            (int) $data['quantity'],
            collect($data)->only(['unit_cost', 'manufacturing_year', 'expiry_date'])->all(),
            $data['note'] ?? 'إدخال بضاعة',
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
