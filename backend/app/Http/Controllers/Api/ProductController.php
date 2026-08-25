<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(name="Admin Products", description="Admin platform product management")
 */
class ProductController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/products",
     *     tags={"Admin Products"},
     *     summary="List products",
     *     security={},
     *     @OA\Response(response=200, description="Paginated list of products")
     * )
     */
    public function index(): JsonResponse
    {
        return $this->jsonResponse(Product::with(['category', 'supplier'])->paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/products",
     *     tags={"Admin Products"},
     *     summary="Create a product",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductRequest")),
     *     @OA\Response(response=201, description="Product created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(ProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['image'] = $this->storeImage($request->file('image'));
        $product = Product::create($data);
        return $this->jsonResponse($product->load(['category', 'supplier']), 201);
    }

    /**
     * @OA\Get(
     *     path="/products/{id}",
     *     tags={"Admin Products"},
     *     summary="Get a product",
     *     security={},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Product $product): JsonResponse
    {
        return $this->jsonResponse($product->load(['category', 'supplier', 'variants.images']));
    }

    /**
     * @OA\Put(
     *     path="/products/{id}",
     *     tags={"Admin Products"},
     *     summary="Update a product",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductRequest")),
     *     @OA\Response(response=200, description="Product updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $this->deleteImage($product->image);
            $data['image'] = $this->storeImage($request->file('image'));
        } else {
            unset($data['image']);
        }
        $product->update($data);
        return $this->jsonResponse($product->load(['category', 'supplier']));
    }

    /**
     * @OA\Delete(
     *     path="/products/{id}",
     *     tags={"Admin Products"},
     *     summary="Delete a product",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Product deleted")
     * )
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->deleteImage($product->image);
        $product->delete();
        return $this->jsonResponse(null, 204);
    }

    private function storeImage(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        return $file->store('products', 'public');
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
