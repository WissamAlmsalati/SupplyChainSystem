<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Admin Suppliers", description="Admin platform supplier management")
 */
class SupplierController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/suppliers",
     *     tags={"Admin Suppliers"},
     *     summary="List suppliers",
     *     @OA\Response(response=200, description="Paginated list of suppliers")
     * )
     */
    public function index(): JsonResponse
    {
        return $this->jsonResponse(Supplier::paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/suppliers",
     *     tags={"Admin Suppliers"},
     *     summary="Create a supplier",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/SupplierRequest")),
     *     @OA\Response(response=201, description="Supplier created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(SupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());
        return $this->jsonResponse($supplier, 201);
    }

    /**
     * @OA\Get(
     *     path="/suppliers/{id}",
     *     tags={"Admin Suppliers"},
     *     summary="Get a supplier",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Supplier details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Supplier $supplier): JsonResponse
    {
        return $this->jsonResponse($supplier->load(['products', 'purchaseOrders']));
    }

    /**
     * @OA\Put(
     *     path="/suppliers/{id}",
     *     tags={"Admin Suppliers"},
     *     summary="Update a supplier",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/SupplierRequest")),
     *     @OA\Response(response=200, description="Supplier updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(SupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());
        return $this->jsonResponse($supplier);
    }

    /**
     * @OA\Delete(
     *     path="/suppliers/{id}",
     *     tags={"Admin Suppliers"},
     *     summary="Delete a supplier",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Supplier deleted")
     * )
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();
        return $this->jsonResponse(null, 204);
    }
}
