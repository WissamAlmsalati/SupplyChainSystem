<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\DelegateRequest;
use App\Models\AppUser;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(name="Delegates", description="Delegate management by admin")
 */
class DelegateController extends BaseApiController
{
    protected function delegateTypeId(): int
    {
        return UserType::where('name', 'delegate')->value('id')
            ?? throw new \RuntimeException('Delegate user type not found');
    }

    /**
     * @OA\Get(
     *     path="/delegates",
     *     tags={"Delegates"},
     *     summary="List delegates",
     *     @OA\Response(response=200, description="Paginated list of delegates")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = AppUser::with(['userType', 'cafe', 'cafeUser'])
            ->where('user_type_id', $this->delegateTypeId());

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('is_available')) {
            $query->whereHas('cafeUser', fn ($q) => $q->where('is_available', $request->boolean('is_available')));
        }

        return $this->jsonResponse($query->orderByDesc('id')->paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/delegates",
     *     tags={"Delegates"},
     *     summary="Create a delegate",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DelegateRequest")),
     *     @OA\Response(response=201, description="Delegate created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(DelegateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_type_id'] = $this->delegateTypeId();
        $data['password_hash'] = Hash::make($data['password']);
        unset($data['password']);

        $delegate = AppUser::create(collect($data)->except(['cafe_id', 'latitude', 'longitude', 'is_available'])->all());
        $delegate->syncCafeUser($data);

        return $this->jsonResponse([
            'id' => $delegate->id,
            'name' => $delegate->name,
            'message' => 'تم إنشاء المندوب بنجاح',
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/delegates/{id}",
     *     tags={"Delegates"},
     *     summary="Get a delegate",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Delegate details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $delegate = AppUser::with(['userType', 'cafe', 'cafeUser', 'delegatedOrders'])
            ->where('user_type_id', $this->delegateTypeId())
            ->findOrFail($id);

        return $this->jsonResponse($delegate);
    }

    /**
     * @OA\Put(
     *     path="/delegates/{id}",
     *     tags={"Delegates"},
     *     summary="Update a delegate",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DelegateRequest")),
     *     @OA\Response(response=200, description="Delegate updated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(DelegateRequest $request, int $id): JsonResponse
    {
        $delegate = AppUser::where('user_type_id', $this->delegateTypeId())->findOrFail($id);

        $data = $request->validated();
        if (! empty($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
        }
        unset($data['password']);

        $delegate->update(collect($data)->except(['cafe_id', 'latitude', 'longitude', 'is_available'])->all());
        $delegate->syncCafeUser($data);

        return $this->jsonResponse($delegate->load(['userType', 'cafe', 'cafeUser']));
    }

    /**
     * @OA\Delete(
     *     path="/delegates/{id}",
     *     tags={"Delegates"},
     *     summary="Delete a delegate",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Delegate deleted")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $delegate = AppUser::where('user_type_id', $this->delegateTypeId())->findOrFail($id);
        $delegate->delete();

        return $this->jsonResponse(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/delegates/{id}/toggle-active",
     *     tags={"Delegates"},
     *     summary="Toggle delegate active status",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Status toggled")
     * )
     */
    public function toggleActive(int $id): JsonResponse
    {
        $delegate = AppUser::where('user_type_id', $this->delegateTypeId())->findOrFail($id);
        $delegate->update(['is_active' => ! $delegate->is_active]);

        return $this->jsonResponse($delegate->load(['userType', 'cafe', 'cafeUser']));
    }

    /**
     * @OA\Put(
     *     path="/delegates/{id}/location",
     *     tags={"Delegates"},
     *     summary="Update delegate location from admin",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="latitude", type="number", format="float"),
     *         @OA\Property(property="longitude", type="number", format="float"),
     *         @OA\Property(property="is_available", type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Location updated")
     * )
     */
    public function updateLocation(\Illuminate\Http\Request $request, int $id): JsonResponse
    {
        $delegate = AppUser::where('user_type_id', $this->delegateTypeId())->findOrFail($id);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_available' => ['boolean'],
        ]);

        $delegate->syncCafeUser($data + ['location_updated_at' => now()]);

        return $this->jsonResponse($delegate->load(['userType', 'cafe', 'cafeUser']));
    }
}
