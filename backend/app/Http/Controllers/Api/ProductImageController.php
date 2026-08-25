<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProductImageRequest;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Admin Products", description="Admin platform product management")
 */
class ProductImageController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->jsonResponse(ProductImage::with('productVariant')->paginate(15));
    }

    public function store(ProductImageRequest $request): JsonResponse
    {
        $image = ProductImage::create($request->validated());
        return $this->jsonResponse($image->load('productVariant'), 201);
    }

    public function show(ProductImage $productImage): JsonResponse
    {
        return $this->jsonResponse($productImage->load(['productVariant']));
    }

    public function update(ProductImageRequest $request, ProductImage $productImage): JsonResponse
    {
        $productImage->update($request->validated());
        return $this->jsonResponse($productImage->load('productVariant'));
    }

    public function destroy(ProductImage $productImage): JsonResponse
    {
        $productImage->delete();
        return $this->jsonResponse(null, 204);
    }
}
