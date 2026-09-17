<?php

namespace App\Http\Controllers\Api;

use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Read-only admin view of customer carts; customers manage carts via /cafe endpoints.
class CartController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Cart::with('user')->withCount('items');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return $this->paginated($query->orderByDesc('id')->paginate(15));
    }

    public function show(Cart $cart): JsonResponse
    {
        return $this->jsonResponse($cart->load(['user', 'items.productVariant.product']));
    }
}
