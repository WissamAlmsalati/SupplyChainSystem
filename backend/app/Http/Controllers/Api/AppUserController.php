<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AppUserRequest;
use App\Models\AppUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AppUserController extends BaseApiController
{
    private const PROFILES = ['adminProfile', 'customerProfile', 'delegateProfile'];

    public function index(Request $request): JsonResponse
    {
        $query = AppUser::with('userType');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('user_type_id')) {
            $ids = array_filter(array_map('intval', explode(',', $request->input('user_type_id'))));
            $query->whereIn('user_type_id', $ids);
        }

        if ($request->filled('user_type')) {
            $names = array_filter(array_map('trim', explode(',', $request->input('user_type'))));
            $query->whereHas('userType', fn ($q) => $q->whereIn('name', $names));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->integer('per_page', 15);

        return $this->jsonResponse($query->orderByDesc('id')->paginate($perPage > 0 ? min($perPage, 10000) : 15));
    }

    public function store(AppUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = AppUser::create($data);

        return $this->jsonResponse($user->load(['userType', ...self::PROFILES]), 201);
    }

    public function show(AppUser $user): JsonResponse
    {
        return $this->jsonResponse($user->load(['userType', 'addresses', 'orders', ...self::PROFILES]));
    }

    public function update(AppUserRequest $request, AppUser $user): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return $this->jsonResponse($user->load(['userType', 'addresses', ...self::PROFILES]));
    }

    public function destroy(AppUser $user): JsonResponse
    {
        $user->tokens()->delete();
        $user->delete();

        return $this->jsonResponse(null, 204);
    }
}
