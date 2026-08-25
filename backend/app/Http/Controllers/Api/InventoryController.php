<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\InventoryRequest;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Admin Inventory", description="Admin platform inventory management")
 */
class InventoryController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/inventory",
     *     tags={"Admin Inventory"},
     *     summary="List inventory records",
     *     @OA\Response(response=200, description="Paginated list of inventory records")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Inventory::with(['warehouse', 'productVariant.product']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('productVariant.product', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('warehouse', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
            });
        }

        return $this->jsonResponse($query->paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/inventory",
     *     tags={"Admin Inventory"},
     *     summary="Create an inventory record",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/InventoryRequest")),
     *     @OA\Response(response=201, description="Inventory record created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(InventoryRequest $request): JsonResponse
    {
        if ($forbidden = $this->requireFeature('add_inventory')) {
            return $forbidden;
        }

        $validated = $request->validated();
        $inventory = Inventory::updateOrCreate(
            [
                'warehouse_id' => $validated['warehouse_id'],
                'product_variant_id' => $validated['product_variant_id'],
            ],
            ['quantity' => $validated['quantity']]
        );

        return $this->jsonResponse($inventory->load(['warehouse', 'productVariant.product']), 201);
    }


    /**
     * @OA\Get(
     *     path="/inventory/{id}",
     *     tags={"Admin Inventory"},
     *     summary="Get an inventory record",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Inventory record details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Inventory $inventory): JsonResponse
    {
        return $this->jsonResponse($inventory->load(['warehouse', 'productVariant.product']));
    }

    /**
     * @OA\Put(
     *     path="/inventory/{id}",
     *     tags={"Admin Inventory"},
     *     summary="Update an inventory record",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/InventoryRequest")),
     *     @OA\Response(response=200, description="Inventory record updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(InventoryRequest $request, Inventory $inventory): JsonResponse
    {
        $inventory->update($request->validated());
        return $this->jsonResponse($inventory->load(['warehouse', 'productVariant.product']));
    }

    /**
     * @OA\Delete(
     *     path="/inventory/{id}",
     *     tags={"Admin Inventory"},
     *     summary="Delete an inventory record",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Inventory record deleted")
     * )
     */
    public function destroy(Inventory $inventory): JsonResponse
    {
        $inventory->delete();
        return $this->jsonResponse(null, 204);
    }
}
