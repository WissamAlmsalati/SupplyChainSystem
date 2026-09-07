<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CafeBranchRequest;
use App\Models\CafeBranch;
use App\Models\PremiumFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Admin Branches", description="Admin platform branch management")
 */
class CafeBranchController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/cafe-branches",
     *     tags={"Admin Branches"},
     *     summary="List cafe branches",
     *     @OA\Response(response=200, description="Paginated list of cafe branches")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = request()->integer('per_page', 15);
        $query = CafeBranch::with(['cafe', 'deliveryZone']);

        if (auth()->user()?->userType?->name === 'cafe') {
            $query->where('cafe_id', auth()->user()->cafe_id);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('street', 'like', "%{$search}%");
            });
        }

        if ($request->filled('cafe_id')) {
            $query->where('cafe_id', $request->integer('cafe_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $this->jsonResponse($query->orderByDesc('id')->paginate($perPage > 0 ? min($perPage, 10000) : 15));
    }

    /**
     * @OA\Post(
     *     path="/cafe-branches",
     *     tags={"Admin Branches"},
     *     summary="Create a cafe branch",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CafeBranchRequest")),
     *     @OA\Response(response=201, description="Cafe branch created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(CafeBranchRequest $request): JsonResponse
    {
        // ponytail: cafe_branches premium feature gates branch creation for everyone;
        // frontend hides the add button, this guard blocks direct API calls.
        if (!PremiumFeature::isActive('cafe_branches')) {
            return $this->jsonResponse(['message' => 'إضافة فروع غير متاحة — الميزة معطلة'], 403);
        }

        $data = $request->validated();

        if (auth()->user()?->userType?->name === 'cafe') {
            $data['cafe_id'] = auth()->user()->cafe_id;
        }

        $branch = CafeBranch::create($data);
        return $this->jsonResponse($branch->load(['cafe', 'deliveryZone']), 201);
    }

    /**
     * @OA\Get(
     *     path="/cafe-branches/{id}",
     *     tags={"Admin Branches"},
     *     summary="Get a cafe branch",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Cafe branch details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(CafeBranch $cafeBranch): JsonResponse
    {
        return $this->jsonResponse($cafeBranch->load(['cafe', 'deliveryZone', 'orders']));
    }

    /**
     * @OA\Put(
     *     path="/cafe-branches/{id}",
     *     tags={"Admin Branches"},
     *     summary="Update a cafe branch",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CafeBranchRequest")),
     *     @OA\Response(response=200, description="Cafe branch updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(CafeBranchRequest $request, CafeBranch $cafeBranch): JsonResponse
    {
        if (auth()->user()?->userType?->name === 'cafe' && $cafeBranch->cafe_id !== auth()->user()->cafe_id) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validated();
        if (auth()->user()?->userType?->name === 'cafe') {
            $data['cafe_id'] = auth()->user()->cafe_id;
        }

        $cafeBranch->update($data);
        return $this->jsonResponse($cafeBranch->load(['cafe', 'deliveryZone']));
    }

    /**
     * @OA\Delete(
     *     path="/cafe-branches/{id}",
     *     tags={"Admin Branches"},
     *     summary="Delete a cafe branch",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Cafe branch deleted")
     * )
     */
    public function destroy(CafeBranch $cafeBranch): JsonResponse
    {
        if (auth()->user()?->userType?->name === 'cafe' && $cafeBranch->cafe_id !== auth()->user()->cafe_id) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $cafeBranch->delete();
        return $this->jsonResponse(null, 204);
    }
}
