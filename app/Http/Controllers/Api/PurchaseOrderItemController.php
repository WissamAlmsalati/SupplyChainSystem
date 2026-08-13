<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PurchaseOrderItemRequest;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Admin Inventory", description="Admin platform inventory management")
 */
class PurchaseOrderItemController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->jsonResponse(PurchaseOrderItem::with(['purchaseOrder', 'productVariant'])->paginate(15));
    }

    public function store(PurchaseOrderItemRequest $request): JsonResponse
    {
        $item = PurchaseOrderItem::create($request->validated());
        return $this->jsonResponse($item->load(['purchaseOrder', 'productVariant']), 201);
    }

    public function show(PurchaseOrderItem $purchaseOrderItem): JsonResponse
    {
        return $this->jsonResponse($purchaseOrderItem->load(['purchaseOrder', 'productVariant']));
    }

    public function update(PurchaseOrderItemRequest $request, PurchaseOrderItem $purchaseOrderItem): JsonResponse
    {
        $purchaseOrderItem->update($request->validated());
        return $this->jsonResponse($purchaseOrderItem->load(['purchaseOrder', 'productVariant']));
    }

    public function destroy(PurchaseOrderItem $purchaseOrderItem): JsonResponse
    {
        $purchaseOrderItem->delete();
        return $this->jsonResponse(null, 204);
    }
}
