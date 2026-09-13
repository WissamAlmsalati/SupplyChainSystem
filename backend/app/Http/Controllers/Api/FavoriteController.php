<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FavoriteController extends BaseApiController
{
    /**
     * @OA\Get(path="/cafe/favorites", tags={"Cafe Favorites"}, summary="My favorite products (newest first, product card shape)", security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="data: products with id, name, image_url, min_price, category_id, default_variant_id, is_favorite, favorited_at"))
     */
    public function index(): JsonResponse
    {
        $products = auth()->user()->favoriteProducts()
            ->where('is_active', true)
            ->whereHas('variants', fn ($v) => $v->where('is_active', true))
            ->with(['allImages', 'variants' => fn ($v) => $v->where('is_active', true)->orderBy('id')])
            ->get()
            ->map(fn (Product $product) => CafeMobileController::productCard($product, true) + [
                'favorited_at' => $product->pivot->created_at,
            ]);

        return $this->jsonResponse(['data' => $products]);
    }

    /**
     * @OA\Get(path="/cafe/favorites/ids", tags={"Cafe Favorites"}, summary="Ids of my favorite products (to mark hearts in lists)", security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="data: [product ids]"))
     */
    public function ids(): JsonResponse
    {
        return $this->jsonResponse(['data' => auth()->user()->favoriteProducts()->pluck('products.id')]);
    }

    /**
     * @OA\Post(path="/cafe/favorites", tags={"Cafe Favorites"}, summary="Add a product to favorites (idempotent)", security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"product_id"}, @OA\Property(property="product_id", type="integer"))),
     *     @OA\Response(response=201, description="product_id, is_favorite=true, favorites_count"),
     *     @OA\Response(response=422, description="Unknown or inactive product"))
     */
    public function store(): JsonResponse
    {
        $data = request()->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ]);

        DB::table('favorites')->insertOrIgnore([
            'user_id' => auth()->id(),
            'product_id' => $data['product_id'],
            'created_at' => now(),
        ]);

        return $this->jsonResponse([
            'data' => [
                'product_id' => (int) $data['product_id'],
                'is_favorite' => true,
                'favorites_count' => auth()->user()->favoriteProducts()->count(),
            ],
            'message' => 'تمت الإضافة إلى المفضلة',
        ], 201);
    }

    /**
     * @OA\Delete(path="/cafe/favorites/{productId}", tags={"Cafe Favorites"}, summary="Remove a product from favorites (idempotent)", security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="product_id, is_favorite=false, favorites_count"))
     */
    public function destroy(int $productId): JsonResponse
    {
        auth()->user()->favoriteProducts()->detach($productId);

        return $this->jsonResponse([
            'product_id' => $productId,
            'is_favorite' => false,
            'favorites_count' => auth()->user()->favoriteProducts()->count(),
            'message' => 'تمت الإزالة من المفضلة',
        ]);
    }
}
