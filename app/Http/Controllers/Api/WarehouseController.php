<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\WarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Admin Warehouses", description="Admin platform warehouse management")
 */
class WarehouseController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/warehouses",
     *     tags={"Admin Warehouses"},
     *     summary="List warehouses",
     *     @OA\Response(response=200, description="Paginated list of warehouses")
     * )
     */
    public function index(): JsonResponse
    {
        return $this->jsonResponse(Warehouse::paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/warehouses",
     *     tags={"Admin Warehouses"},
     *     summary="Create a warehouse",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/WarehouseRequest")),
     *     @OA\Response(response=201, description="Warehouse created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(WarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create($request->validated());
        return $this->jsonResponse($warehouse, 201);
    }

    /**
     * @OA\Get(
     *     path="/warehouses/{id}",
     *     tags={"Admin Warehouses"},
     *     summary="Get a warehouse",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Warehouse details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        return $this->jsonResponse($warehouse->load(['inventories', 'purchaseOrders']));
    }

    /**
     * @OA\Put(
     *     path="/warehouses/{id}",
     *     tags={"Admin Warehouses"},
     *     summary="Update a warehouse",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/WarehouseRequest")),
     *     @OA\Response(response=200, description="Warehouse updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(WarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update($request->validated());
        return $this->jsonResponse($warehouse);
    }

    /**
     * @OA\Delete(
     *     path="/warehouses/{id}",
     *     tags={"Admin Warehouses"},
     *     summary="Delete a warehouse",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Warehouse deleted")
     * )
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();
        return $this->jsonResponse(null, 204);
    }
}
