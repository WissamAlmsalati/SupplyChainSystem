<?php

namespace App\Http\Controllers\Api;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\Api\PurchaseOrderItemRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\JsonResponse;

class PurchaseOrderItemController extends BaseApiController
{
    private function isDraft(int $purchaseOrderId): bool
    {
        return PurchaseOrder::whereKey($purchaseOrderId)->value('status') === PurchaseOrderStatus::Draft->value;
    }

    private function lockedResponse(): JsonResponse
    {
        return $this->jsonResponse(['message' => 'لا يمكن تعديل أصناف أمر شراء غير مسودة'], 422);
    }

    public function index(): JsonResponse
    {
        return $this->jsonResponse(PurchaseOrderItem::with(['purchaseOrder', 'productVariant'])->orderByDesc('id')->paginate(15));
    }

    public function store(PurchaseOrderItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! $this->isDraft($data['purchase_order_id'])) {
            return $this->lockedResponse();
        }

        $item = PurchaseOrderItem::create($data);

        return $this->jsonResponse($item->load(['purchaseOrder', 'productVariant']), 201);
    }

    public function show(PurchaseOrderItem $purchaseOrderItem): JsonResponse
    {
        return $this->jsonResponse($purchaseOrderItem->load(['purchaseOrder', 'productVariant']));
    }

    public function update(PurchaseOrderItemRequest $request, PurchaseOrderItem $purchaseOrderItem): JsonResponse
    {
        $data = $request->validated();
        if (! $this->isDraft($purchaseOrderItem->purchase_order_id) || ! $this->isDraft($data['purchase_order_id'])) {
            return $this->lockedResponse();
        }

        $purchaseOrderItem->update($data);

        return $this->jsonResponse($purchaseOrderItem->load(['purchaseOrder', 'productVariant']));
    }

    public function destroy(PurchaseOrderItem $purchaseOrderItem): JsonResponse
    {
        if (! $this->isDraft($purchaseOrderItem->purchase_order_id)) {
            return $this->lockedResponse();
        }

        $purchaseOrderItem->delete();

        return $this->jsonResponse(null, 204);
    }
}
