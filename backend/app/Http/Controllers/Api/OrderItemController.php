<?php

namespace App\Http\Controllers\Api;

use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;

// Read-only; order lines are written only when an order is placed.
class OrderItemController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->jsonResponse(OrderItem::with(['order', 'productVariant'])->orderByDesc('id')->paginate(15));
    }

    public function show(OrderItem $orderItem): JsonResponse
    {
        return $this->jsonResponse($orderItem->load(['order', 'productVariant']));
    }
}
