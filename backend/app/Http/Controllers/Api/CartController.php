<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CartRequest;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Storefront", description="Public storefront endpoints")
 */
class CartController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/carts",
     *     tags={"Storefront"},
     *     summary="List carts",
     *     @OA\Response(response=200, description="Paginated list of carts")
     * )
     */
    public function index(): JsonResponse
    {
        return $this->jsonResponse(Cart::with(['user', 'branch'])->orderByDesc('id')->paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/carts",
     *     tags={"Storefront"},
     *     summary="Create a cart",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CartRequest")),
     *     @OA\Response(response=201, description="Cart created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(CartRequest $request): JsonResponse
    {
        $cart = Cart::create($request->validated());
        return $this->jsonResponse($cart->load(['user', 'branch']), 201);
    }

    /**
     * @OA\Get(
     *     path="/carts/{id}",
     *     tags={"Storefront"},
     *     summary="Get a cart",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Cart details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Cart $cart): JsonResponse
    {
        return $this->jsonResponse($cart->load(['user', 'branch', 'items.productVariant']));
    }

    /**
     * @OA\Put(
     *     path="/carts/{id}",
     *     tags={"Storefront"},
     *     summary="Update a cart",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CartRequest")),
     *     @OA\Response(response=200, description="Cart updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(CartRequest $request, Cart $cart): JsonResponse
    {
        $cart->update($request->validated());
        return $this->jsonResponse($cart->load(['user', 'branch']));
    }

    /**
     * @OA\Delete(
     *     path="/carts/{id}",
     *     tags={"Storefront"},
     *     summary="Delete a cart",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Cart deleted")
     * )
     */
    public function destroy(Cart $cart): JsonResponse
    {
        $cart->delete();
        return $this->jsonResponse(null, 204);
    }
}
