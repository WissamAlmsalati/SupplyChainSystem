<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
    public function index(Request $request): JsonResponse
    {
        $query = Category::with('parentCategory');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->input('parent') === 'root') {
            $query->whereNull('parent_category_id');
        } elseif ($request->input('parent') === 'sub') {
            $query->whereNotNull('parent_category_id');
        }

        $perPage = $request->integer('per_page', 15);

        return $this->jsonResponse($query->orderByDesc('id')->paginate($perPage > 0 ? min($perPage, 1000) : 15));
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
        $data = $request->validated();
        $data['image'] = $this->storeImage($request->file('image'));

        $category = Category::create($data);

        return $this->jsonResponse($category->load('parentCategory'), 201);
    }

    private function storeImage(?UploadedFile $file): ?string
    {
        return $file?->store('categories', 'public');
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
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
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $this->deleteImage($category->image);
            $data['image'] = $this->storeImage($request->file('image'));
        } else {
            unset($data['image']);
        }

        $category->update($data);

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
        $this->deleteImage($category->image);
        $category->delete();

        return $this->jsonResponse(null, 204);
    }
}
