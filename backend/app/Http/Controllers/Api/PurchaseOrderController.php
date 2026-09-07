<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Admin Inventory", description="Admin platform inventory management")
 */
class PurchaseOrderController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/purchase-orders",
     *     tags={"Admin Inventory"},
     *     summary="List purchase orders",
     *     @OA\Response(response=200, description="Paginated list of purchase orders")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['warehouse']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $this->jsonResponse($query->orderByDesc('id')->paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/purchase-orders",
     *     tags={"Admin Inventory"},
     *     summary="Create a purchase order",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/PurchaseOrderRequest")),
     *     @OA\Response(response=201, description="Purchase order created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(PurchaseOrderRequest $request): JsonResponse
    {
        $purchaseOrder = PurchaseOrder::create($request->validated());
        return $this->jsonResponse($purchaseOrder->load(['warehouse']), 201);
    }

    /**
     * @OA\Get(
     *     path="/purchase-orders/{id}",
     *     tags={"Admin Inventory"},
     *     summary="Get a purchase order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Purchase order details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return $this->jsonResponse($purchaseOrder->load(['warehouse', 'items.productVariant']));
    }

    /**
     * @OA\Put(
     *     path="/purchase-orders/{id}",
     *     tags={"Admin Inventory"},
     *     summary="Update a purchase order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/PurchaseOrderRequest")),
     *     @OA\Response(response=200, description="Purchase order updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->update($request->validated());
        return $this->jsonResponse($purchaseOrder->load(['warehouse']));
    }

    /**
     * @OA\Delete(
     *     path="/purchase-orders/{id}",
     *     tags={"Admin Inventory"},
     *     summary="Delete a purchase order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Purchase order deleted")
     * )
     */
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->delete();
        return $this->jsonResponse(null, 204);
    }
}
