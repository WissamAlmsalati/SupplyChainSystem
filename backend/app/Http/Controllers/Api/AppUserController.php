<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Requests\Api\AppUserRequest;
use App\Models\AppUser;
use App\Models\UserType;
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

        return $this->paginated($query->orderByDesc('id')->paginate($perPage > 0 ? min($perPage, 10000) : 15));
    }

    /**
     * The super admin bypasses every permission check, so who may create or
     * change one cannot be left to USERS_CREATE / USERS_EDIT: any role holding
     * those could otherwise promote itself and own the system.
     */
    private function guardSuperAdmin(?AppUser $target, ?int $newTypeId): ?JsonResponse
    {
        if (auth()->user()?->hasRole(UserRole::SuperAdmin)) {
            return null;
        }

        $superId = UserType::where('name', UserRole::SuperAdmin->value)->value('id');
        if ($target?->user_type_id === $superId || ($newTypeId !== null && $newTypeId === $superId)) {
            return $this->jsonResponse(['message' => 'حساب المدير العام لا يديره إلا مدير عام'], 403);
        }

        return null;
    }

    public function store(AppUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($refused = $this->guardSuperAdmin(null, isset($data['user_type_id']) ? (int) $data['user_type_id'] : null)) {
            return $refused;
        }
        $data['password'] = Hash::make($data['password']);

        $user = AppUser::create($data);

        return $this->jsonResponse($user->load(['userType', ...self::PROFILES]), 201);
    }

    private function isLastSuperAdmin(AppUser $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin)
            && AppUser::where('is_active', true)->whereHas('userType', fn ($q) => $q->where('name', UserRole::SuperAdmin->value))->count() <= 1;
    }

    public function show(AppUser $user): JsonResponse
    {
        return $this->jsonResponse($user->load(['userType', 'addresses', 'orders', ...self::PROFILES]));
    }

    public function update(AppUserRequest $request, AppUser $user): JsonResponse
    {
        $data = $request->validated();
        if ($refused = $this->guardSuperAdmin($user, isset($data['user_type_id']) ? (int) $data['user_type_id'] : null)) {
            return $refused;
        }
        // Locking yourself out, or demoting the last super admin, leaves nobody to undo it.
        if ($user->id === auth()->id() && array_key_exists('is_active', $data) && ! $data['is_active']) {
            return $this->jsonResponse(['message' => 'لا يمكنك تعطيل حسابك بنفسك'], 422);
        }
        if ($this->isLastSuperAdmin($user) && ((isset($data['user_type_id']) && (int) $data['user_type_id'] !== $user->user_type_id) || (array_key_exists('is_active', $data) && ! $data['is_active']))) {
            return $this->jsonResponse(['message' => 'هذا آخر مدير عام في النظام'], 422);
        }
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
        if ($refused = $this->guardSuperAdmin($user, null)) {
            return $refused;
        }
        if ($user->id === auth()->id()) {
            return $this->jsonResponse(['message' => 'لا يمكنك حذف حسابك بنفسك'], 422);
        }
        if ($this->isLastSuperAdmin($user)) {
            return $this->jsonResponse(['message' => 'هذا آخر مدير عام في النظام'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->jsonResponse(null, 204);
    }
}
