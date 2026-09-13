<?php

namespace App\Http\Controllers\Api;

use App\Enums\CartType;
use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
use App\Http\Requests\Api\RecurringCartRequest;
use App\Models\Address;
use App\Models\Cart;
use App\Services\OrderPlacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Named carts the customer builds once and re-orders from ("the order that always repeats").
 */
class RecurringCartController extends BaseApiController
{
    private function scope()
    {
        return Cart::recurring()->where('user_id', auth()->id());
    }

    private function payload(Cart $cart): Cart
    {
        $cart->load('items.productVariant.product');
        $cart->setAttribute('subtotal', $cart->subtotal());

        return $cart;
    }

    /**
     * @OA\Get(path="/cafe/recurring-carts", tags={"Cafe Recurring Carts"}, summary="List own recurring carts",
     *     @OA\Response(response=200, description="Carts with items and current subtotal"))
     */
    public function index(): JsonResponse
    {
        $carts = $this->scope()->orderBy('name')->get()->map(fn (Cart $cart) => $this->payload($cart));

        return $this->jsonResponse(['data' => $carts]);
    }

    /**
     * @OA\Post(path="/cafe/recurring-carts", tags={"Cafe Recurring Carts"}, summary="Create a recurring cart",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RecurringCartRequest")),
     *     @OA\Response(response=201, description="Cart created"))
     */
    public function store(RecurringCartRequest $request): JsonResponse
    {
        $data = $request->validated();

        $cart = DB::transaction(function () use ($data) {
            $cart = Cart::create([
                'user_id' => auth()->id(),
                'type' => CartType::Recurring,
                'name' => $data['name'],
            ]);
            $cart->items()->createMany($data['items']);

            return $cart;
        });

        return $this->jsonResponse(['data' => $this->payload($cart)], 201);
    }

    /**
     * @OA\Get(path="/cafe/recurring-carts/{id}", tags={"Cafe Recurring Carts"}, summary="Recurring cart details",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Cart"))
     */
    public function show(int $id): JsonResponse
    {
        return $this->jsonResponse($this->payload($this->scope()->findOrFail($id)));
    }

    /**
     * @OA\Put(path="/cafe/recurring-carts/{id}", tags={"Cafe Recurring Carts"}, summary="Rename and/or replace the items",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/RecurringCartRequest")),
     *     @OA\Response(response=200, description="Cart updated"))
     */
    public function update(RecurringCartRequest $request, int $id): JsonResponse
    {
        $cart = $this->scope()->findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($cart, $data) {
            if (isset($data['name'])) {
                $cart->update(['name' => $data['name']]);
            }
            if (isset($data['items'])) {
                $cart->items()->delete();
                $cart->items()->createMany($data['items']);
            }
        });

        return $this->jsonResponse($this->payload($cart));
    }

    /**
     * @OA\Delete(path="/cafe/recurring-carts/{id}", tags={"Cafe Recurring Carts"}, summary="Delete a recurring cart",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Cart deleted"))
     */
    public function destroy(int $id): JsonResponse
    {
        $this->scope()->findOrFail($id)->delete();

        return $this->jsonResponse(['message' => 'تم حذف السلة']);
    }

    /**
     * @OA\Post(path="/cafe/recurring-carts/{id}/order", tags={"Cafe Recurring Carts"}, summary="Place an order from a recurring cart",
     *     description="The cart is kept as-is so it can be ordered again; prices are the current ones.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"address_id"}, @OA\Property(property="address_id", type="integer"), @OA\Property(property="payment_method", type="string", enum={"cash","wallet"}, default="cash"))),
     *     @OA\Response(response=201, description="Order created"),
     *     @OA\Response(response=409, description="Insufficient stock"))
     */
    public function order(Request $request, int $id, OrderPlacementService $placement): JsonResponse
    {
        $data = $request->validate([
            'address_id' => ['required', 'integer'],
            'payment_method' => ['nullable', Rule::in([PaymentMethod::Cash->value, PaymentMethod::Wallet->value])],
        ]);

        $cart = $this->scope()->with('items')->findOrFail($id);
        if ($cart->items->isEmpty()) {
            return $this->jsonResponse(['message' => 'السلة فارغة'], 400);
        }

        $address = Address::where('user_id', auth()->id())->findOrFail($data['address_id']);

        $order = $placement->place(auth()->user(), $address, $cart->items->toArray(), OrderSource::App, $cart, null, PaymentMethod::from($data['payment_method'] ?? 'cash'));

        return $this->jsonResponse([
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'delegate_id' => $order->delegate_id,
            'message' => 'تم إنشاء الطلب بنجاح',
        ], 201);
    }
}
