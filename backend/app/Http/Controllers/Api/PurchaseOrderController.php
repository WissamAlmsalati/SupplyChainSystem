<?php

namespace App\Http\Controllers\Api;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Http\Requests\Api\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Goods entering a warehouse. Drafts are editable; receiving adds the stock.
class PurchaseOrderController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['warehouse', 'createdBy:id,name'])->withCount('items');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $this->jsonResponse($query->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    public function store(PurchaseOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $purchaseOrder = DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create(collect($data)->only(['warehouse_id', 'note'])->all());
            $po->items()->createMany($data['items'] ?? []);

            return $po;
        });

        return $this->jsonResponse($purchaseOrder->load(['warehouse', 'items.productVariant.product']), 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->jsonResponse($purchaseOrder->load(['warehouse', 'createdBy:id,name', 'items.productVariant.product']));
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            return $this->jsonResponse(['message' => 'لا يمكن تعديل أمر شراء غير مسودة'], 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($purchaseOrder, $data) {
            $purchaseOrder->update(collect($data)->only(['warehouse_id', 'note'])->all());
            if (array_key_exists('items', $data)) {
                $purchaseOrder->items()->delete();
                $purchaseOrder->items()->createMany($data['items'] ?? []);
            }
        });

        return $this->jsonResponse($purchaseOrder->load(['warehouse', 'items.productVariant.product']));
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status === PurchaseOrderStatus::Received) {
            return $this->jsonResponse(['message' => 'لا يمكن حذف أمر شراء تم استلامه'], 422);
        }

        $purchaseOrder->delete();

        return $this->jsonResponse(null, 204);
    }

    // Adds every item to the warehouse stock and locks the purchase order.
    public function receive(PurchaseOrder $purchaseOrder, StockService $stock): JsonResponse
    {
        $received = DB::transaction(function () use ($purchaseOrder, $stock) {
            $po = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->first();
            if ($po->status !== PurchaseOrderStatus::Draft) {
                return false;
            }

            $po->load('items');
            if ($po->items->isEmpty()) {
                return null;
            }

            foreach ($po->items as $item) {
                $stock->adjust($po->warehouse_id, $item->product_variant_id, $item->quantity, StockMovementType::Purchase, $po);
            }

            $po->update(['status' => PurchaseOrderStatus::Received, 'received_at' => now()]);

            return true;
        });

        if ($received === false) {
            return $this->jsonResponse(['message' => 'أمر الشراء ليس مسودة'], 422);
        }
        if ($received === null) {
            return $this->jsonResponse(['message' => 'أمر الشراء لا يحتوي على أصناف'], 422);
        }

        return $this->jsonResponse($purchaseOrder->fresh(['warehouse', 'items.productVariant.product']));
    }

    public function cancel(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            return $this->jsonResponse(['message' => 'لا يمكن إلغاء أمر شراء غير مسودة'], 422);
        }

        $purchaseOrder->update(['status' => PurchaseOrderStatus::Cancelled]);

        return $this->jsonResponse($purchaseOrder->load('warehouse'));
    }
}
