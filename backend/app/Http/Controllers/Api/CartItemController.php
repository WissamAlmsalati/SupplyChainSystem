<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CartItemRequest;
use App\Models\CartItem;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Storefront", description="Public storefront endpoints")
 */
class CartItemController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->jsonResponse(CartItem::with(['cart', 'productVariant'])->paginate(15));
    }

    public function store(CartItemRequest $request): JsonResponse
    {
        $item = CartItem::create($request->validated());
        return $this->jsonResponse($item->load(['cart', 'productVariant']), 201);
    }

    public function show(CartItem $cartItem): JsonResponse
    {
        return $this->jsonResponse($cartItem->load(['cart', 'productVariant']));
    }

    public function update(CartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        $cartItem->update($request->validated());
        return $this->jsonResponse($cartItem->load(['cart', 'productVariant']));
    }

    public function destroy(CartItem $cartItem): JsonResponse
    {
        $cartItem->delete();
        return $this->jsonResponse(null, 204);
    }
}
