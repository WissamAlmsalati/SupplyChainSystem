<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Admin Categories", description="Admin platform category management")
 */
class CategoryController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/categories",
     *     tags={"Admin Categories"},
     *     summary="List categories",
     *     security={},
     *     @OA\Response(response=200, description="Paginated list of categories")
     * )
     */
    public function index(): JsonResponse
    {
        return $this->jsonResponse(Category::with('parentCategory')->paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/categories",
     *     tags={"Admin Categories"},
     *     summary="Create a category",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CategoryRequest")),
     *     @OA\Response(response=201, description="Category created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());
        return $this->jsonResponse($category->load('parentCategory'), 201);
    }

    /**
     * @OA\Get(
     *     path="/categories/{id}",
     *     tags={"Admin Categories"},
     *     summary="Get a category",
     *     security={},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Category details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Category $category): JsonResponse
    {
        return $this->jsonResponse($category->load(['parentCategory', 'childCategories', 'products']));
    }

    /**
     * @OA\Put(
     *     path="/categories/{id}",
     *     tags={"Admin Categories"},
     *     summary="Update a category",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CategoryRequest")),
     *     @OA\Response(response=200, description="Category updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());
        return $this->jsonResponse($category->load('parentCategory'));
    }

    /**
     * @OA\Delete(
     *     path="/categories/{id}",
     *     tags={"Admin Categories"},
     *     summary="Delete a category",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Category deleted")
     * )
     */
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();
        return $this->jsonResponse(null, 204);
    }
}
