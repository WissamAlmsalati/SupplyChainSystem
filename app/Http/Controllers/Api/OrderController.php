<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\OrderRequest;
use App\Models\CafeBranch;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Admin Orders", description="Admin platform order management")
 */
class OrderController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/orders",
     *     tags={"Admin Orders"},
     *     summary="List orders",
     *     @OA\Response(response=200, description="Paginated list of orders")
     * )
     */
    protected function isCafeAdmin(): bool
    {
        return auth()->user()?->userType?->name === 'cafe';
    }

    protected function cafeAdminCafeId(): ?int
    {
        return auth()->user()?->cafe_id;
    }

    protected function orderQuery()
    {
        $query = Order::with(['user', 'branch', 'deliveryZone']);

        if ($this->isCafeAdmin()) {
            $query->whereHas('branch', fn ($q) => $q->where('cafe_id', $this->cafeAdminCafeId()));
        }

        return $query;
    }

    protected function canAccessOrder(Order $order): bool
    {
        if (! $this->isCafeAdmin()) {
            return true;
        }

        return $order->branch?->cafe_id === $this->cafeAdminCafeId();
    }

    public function index(): JsonResponse
    {
        return $this->jsonResponse($this->orderQuery()->paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/orders",
     *     tags={"Admin Orders"},
     *     summary="Create an order",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/OrderRequest")),
     *     @OA\Response(response=201, description="Order created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(OrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        if ($this->isCafeAdmin()) {
            $branch = CafeBranch::find($data['branch_id'] ?? null);
            if (! $branch || $branch->cafe_id !== $this->cafeAdminCafeId()) {
                return $this->jsonResponse(['message' => 'غير مصرح'], 403);
            }
        }

        $order = DB::transaction(function () use ($data, $items) {
            $order = Order::create($data);
            if ($items) {
                $order->items()->createMany($items);
            }

            return $order;
        });

        return $this->jsonResponse($order->load(['user', 'branch', 'deliveryZone', 'items.productVariant']), 201);
    }

    /**
     * @OA\Get(
     *     path="/orders/{id}",
     *     tags={"Admin Orders"},
     *     summary="Get an order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Order details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Order $order): JsonResponse
    {
        if (! $this->canAccessOrder($order)) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        return $this->jsonResponse($order->load(['user', 'branch', 'deliveryZone', 'items.productVariant', 'payments', 'statusLogs']));
    }

    /**
     * @OA\Put(
     *     path="/orders/{id}",
     *     tags={"Admin Orders"},
     *     summary="Update an order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/OrderRequest")),
     *     @OA\Response(response=200, description="Order updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(OrderRequest $request, Order $order): JsonResponse
    {
        if (! $this->canAccessOrder($order)) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validated();
        unset($data['items']);

        if ($this->isCafeAdmin() && isset($data['branch_id'])) {
            $branch = CafeBranch::find($data['branch_id']);
            if (! $branch || $branch->cafe_id !== $this->cafeAdminCafeId()) {
                return $this->jsonResponse(['message' => 'غير مصرح'], 403);
            }
        }

        $order->update($data);
        return $this->jsonResponse($order->load(['user', 'branch', 'deliveryZone', 'items.productVariant']));
    }

    /**
     * @OA\Delete(
     *     path="/orders/{id}",
     *     tags={"Admin Orders"},
     *     summary="Delete an order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Order deleted")
     * )
     */
    public function destroy(Order $order): JsonResponse
    {
        if (! $this->canAccessOrder($order)) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $order->delete();
        return $this->jsonResponse(null, 204);
    }
}
