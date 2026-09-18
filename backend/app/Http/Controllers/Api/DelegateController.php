<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Requests\Api\DelegateRequest;
use App\Models\AppUser;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(name="Delegates", description="Delegate management (admin)")
 */
class DelegateController extends BaseApiController
{
    private const PROFILE_FIELDS = ['latitude', 'longitude', 'is_available'];

    protected function delegateTypeId(): int
    {
        return UserType::where('name', UserRole::Delegate->value)->value('id')
            ?? throw new \RuntimeException('Delegate user type not found');
    }

    protected function delegates()
    {
        return AppUser::with(['userType', 'delegateProfile'])
            ->where('user_type_id', $this->delegateTypeId());
    }

    /**
     * @OA\Get(path="/delegates", tags={"Delegates"}, summary="List delegates",
     *
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="is_available", in="query", @OA\Schema(type="boolean")),
     *
     *     @OA\Response(response=200, description="Paginated delegates with delegate_profile"))
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->delegates();

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
            $query->whereHas('delegateProfile', fn ($q) => $q->where('is_available', $request->boolean('is_available')));
        }

        return $this->paginated($query->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    /**
     * @OA\Post(path="/delegates", tags={"Delegates"}, summary="Create a delegate",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DelegateRequest")),
     *
     *     @OA\Response(response=201, description="Delegate created"))
     */
    public function store(DelegateRequest $request): JsonResponse
    {
        $data = $request->validated();

        $delegate = DB::transaction(function () use ($data) {
            $delegate = AppUser::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile_number' => $data['mobile_number'] ?? null,
                'password' => Hash::make($data['password']),
                'user_type_id' => $this->delegateTypeId(),
                'is_active' => $data['is_active'] ?? true,
            ]);
            $this->updateProfile($delegate, $data);

            return $delegate;
        });

        return $this->jsonResponse([
            'id' => $delegate->id,
            'name' => $delegate->name,
            'message' => 'تم إنشاء المندوب بنجاح',
        ], 201);
    }

    /**
     * @OA\Get(path="/delegates/{id}", tags={"Delegates"}, summary="Delegate details",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Delegate with profile and orders"))
     */
    public function show(int $id): JsonResponse
    {
        return $this->jsonResponse($this->delegates()->with('delegatedOrders')->findOrFail($id));
    }

    /**
     * @OA\Put(path="/delegates/{id}", tags={"Delegates"}, summary="Update a delegate",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DelegateRequest")),
     *
     *     @OA\Response(response=200, description="Delegate updated"))
     */
    public function update(DelegateRequest $request, int $id): JsonResponse
    {
        $delegate = $this->delegates()->findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($delegate, $data) {
            $userData = collect($data)->only(['name', 'email', 'mobile_number', 'is_active'])->all();
            if (! empty($data['password'])) {
                $userData['password'] = Hash::make($data['password']);
            }
            $delegate->update($userData);
            $this->updateProfile($delegate, $data);
        });

        return $this->jsonResponse($delegate->fresh(['userType', 'delegateProfile']));
    }

    /**
     * @OA\Delete(path="/delegates/{id}", tags={"Delegates"}, summary="Delete a delegate (soft delete)",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=204, description="Delegate deleted"))
     */
    public function destroy(int $id): JsonResponse
    {
        $this->delegates()->findOrFail($id)->delete();

        return $this->jsonResponse(null, 204);
    }

    public function toggleActive(int $id): JsonResponse
    {
        $delegate = $this->delegates()->findOrFail($id);
        $delegate->update(['is_active' => ! $delegate->is_active]);

        return $this->jsonResponse($delegate->fresh(['userType', 'delegateProfile']));
    }

    public function updateLocation(Request $request, int $id): JsonResponse
    {
        $delegate = $this->delegates()->findOrFail($id);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_available' => ['boolean'],
        ]);

        $this->updateProfile($delegate, $data + ['location_updated_at' => now()]);

        return $this->jsonResponse($delegate->fresh(['userType', 'delegateProfile']));
    }

    private function updateProfile(AppUser $delegate, array $data): void
    {
        $profileData = collect($data)->only([...self::PROFILE_FIELDS, 'location_updated_at'])->all();

        if (isset($profileData['latitude'], $profileData['longitude']) && ! isset($profileData['location_updated_at'])) {
            $profileData['location_updated_at'] = now();
        }

        if ($profileData) {
            $delegate->delegateProfile()->updateOrCreate(['user_id' => $delegate->id], $profileData);
        }
    }
}
