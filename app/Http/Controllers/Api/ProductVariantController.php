<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProductVariantRequest;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Admin Products", description="Admin platform product management")
 */
class ProductVariantController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/product-variants",
     *     tags={"Admin Products"},
     *     summary="List product variants",
     *     @OA\Response(response=200, description="Paginated list of product variants")
     * )
     */
    public function index(): JsonResponse
    {
        return $this->jsonResponse(ProductVariant::with('product')->paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/product-variants",
     *     tags={"Admin Products"},
     *     summary="Create a product variant",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductVariantRequest")),
     *     @OA\Response(response=201, description="Product variant created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(ProductVariantRequest $request): JsonResponse
    {
        $variant = ProductVariant::create($request->validated());
        return $this->jsonResponse($variant->load('product'), 201);
    }

    /**
     * @OA\Get(
     *     path="/product-variants/{id}",
     *     tags={"Admin Products"},
     *     summary="Get a product variant",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product variant details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(ProductVariant $productVariant): JsonResponse
    {
        return $this->jsonResponse($productVariant->load(['product', 'inventories']));
    }

    /**
     * @OA\Put(
     *     path="/product-variants/{id}",
     *     tags={"Admin Products"},
     *     summary="Update a product variant",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductVariantRequest")),
     *     @OA\Response(response=200, description="Product variant updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(ProductVariantRequest $request, ProductVariant $productVariant): JsonResponse
    {
        $productVariant->update($request->validated());
        return $this->jsonResponse($productVariant->load('product'));
    }

    /**
     * @OA\Delete(
     *     path="/product-variants/{id}",
     *     tags={"Admin Products"},
     *     summary="Delete a product variant",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Product variant deleted")
     * )
     */
    public function destroy(ProductVariant $productVariant): JsonResponse
    {
        $productVariant->delete();
        return $this->jsonResponse(null, 204);
    }
}
