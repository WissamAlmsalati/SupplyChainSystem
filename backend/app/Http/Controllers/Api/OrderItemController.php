<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\OrderItemRequest;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Admin Orders", description="Admin platform order management")
 */
class OrderItemController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->jsonResponse(OrderItem::with(['order', 'productVariant'])->paginate(15));
    }

    public function store(OrderItemRequest $request): JsonResponse
    {
        $item = OrderItem::create($request->validated());
        return $this->jsonResponse($item->load(['order', 'productVariant']), 201);
    }

    public function show(OrderItem $orderItem): JsonResponse
    {
        return $this->jsonResponse($orderItem->load(['order', 'productVariant']));
    }

    public function update(OrderItemRequest $request, OrderItem $orderItem): JsonResponse
    {
        $orderItem->update($request->validated());
        return $this->jsonResponse($orderItem->load(['order', 'productVariant']));
    }

    public function destroy(OrderItem $orderItem): JsonResponse
    {
        $orderItem->delete();
        return $this->jsonResponse(null, 204);
    }
}
