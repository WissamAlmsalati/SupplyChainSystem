<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProductImageRequest;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(name="Admin Products", description="Admin platform product management")
 */
class ProductImageController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->jsonResponse(ProductImage::with('productVariant')->orderByDesc('id')->paginate(15));
    }

    public function store(ProductImageRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('product-images', 'public');
            $data['url'] = null;
        }

        $image = ProductImage::create($data);
        return $this->jsonResponse($image->load('productVariant'), 201);
    }

    public function show(ProductImage $productImage): JsonResponse
    {
        return $this->jsonResponse($productImage->load(['productVariant']));
    }

    public function update(ProductImageRequest $request, ProductImage $productImage): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($productImage->image) {
                Storage::disk('public')->delete($productImage->image);
            }
            $data['image'] = $request->file('image')->store('product-images', 'public');
            $data['url'] = null;
        }

        $productImage->update($data);
        return $this->jsonResponse($productImage->load('productVariant'));
    }

    public function destroy(ProductImage $productImage): JsonResponse
    {
        if ($productImage->image) {
            Storage::disk('public')->delete($productImage->image);
        }
        $productImage->delete();
        return $this->jsonResponse(null, 204);
    }
}
