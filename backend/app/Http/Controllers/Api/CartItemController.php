<?php

namespace App\Http\Controllers\Api;

use App\Models\CartItem;
use Illuminate\Http\JsonResponse;

// Read-only; items change through the customer cart endpoints.
class CartItemController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->paginated(CartItem::with(['cart', 'productVariant'])->orderByDesc('id')->paginate(15));
    }

    public function show(CartItem $cartItem): JsonResponse
    {
        return $this->jsonResponse($cartItem->load(['cart', 'productVariant']));
    }
}
