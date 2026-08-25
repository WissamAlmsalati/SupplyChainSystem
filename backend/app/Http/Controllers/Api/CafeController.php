<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CafeRequest;
use App\Models\Cafe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(name="Admin Cafes", description="Admin platform cafe management")
 */
class CafeController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/cafes",
     *     tags={"Admin Cafes"},
     *     summary="List cafes",
     *     @OA\Response(response=200, description="Paginated list of cafes")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cafe::with('createdByAdmin');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_info', 'like', "%{$search}%");
            });
        }

        return $this->jsonResponse($query->paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/cafes",
     *     tags={"Admin Cafes"},
     *     summary="Create a cafe",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CafeRequest")),
     *     @OA\Response(response=201, description="Cafe created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(CafeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['image'] = $this->storeImage($request->file('image'));
        $cafe = Cafe::create($data);
        return $this->jsonResponse($cafe->load('createdByAdmin'), 201);
    }

    /**
     * @OA\Get(
     *     path="/cafes/{id}",
     *     tags={"Admin Cafes"},
     *     summary="Get a cafe",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Cafe details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Cafe $cafe): JsonResponse
    {
        return $this->jsonResponse($cafe->load(['createdByAdmin', 'branches']));
    }

    /**
     * @OA\Put(
     *     path="/cafes/{id}",
     *     tags={"Admin Cafes"},
     *     summary="Update a cafe",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CafeRequest")),
     *     @OA\Response(response=200, description="Cafe updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(CafeRequest $request, Cafe $cafe): JsonResponse
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $this->deleteImage($cafe->image);
            $data['image'] = $this->storeImage($request->file('image'));
        } else {
            unset($data['image']);
        }
        $cafe->update($data);
        return $this->jsonResponse($cafe->load('createdByAdmin'));
    }

    /**
     * @OA\Delete(
     *     path="/cafes/{id}",
     *     tags={"Admin Cafes"},
     *     summary="Delete a cafe",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Cafe deleted")
     * )
     */
    public function destroy(Cafe $cafe): JsonResponse
    {
        $this->deleteImage($cafe->image);
        $cafe->delete();
        return $this->jsonResponse(null, 204);
    }

    private function storeImage(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        return $file->store('cafes', 'public');
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
