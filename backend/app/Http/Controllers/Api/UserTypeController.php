<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Requests\Api\UserTypeRequest;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Admin Roles", description="Admin platform roles and permissions")
 */
class UserTypeController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = UserType::with('permissions');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        return $this->paginated($query->orderByDesc('id')->paginate(15));
    }

    public function store(UserTypeRequest $request): JsonResponse
    {
        if ($forbidden = $this->requireFeature('add_role')) {
            return $forbidden;
        }

        if ($refused = $this->guardRole($request, null)) {
            return $refused;
        }

        $userType = UserType::create($request->validated());
        $userType->permissions()->sync($request->input('permission_ids', []));

        return $this->jsonResponse($userType->load('permissions'), 201);
    }

    public function show(UserType $userType): JsonResponse
    {
        return $this->jsonResponse($userType->load(['permissions', 'appUsers']));
    }

    /**
     * Roles are where privileges come from, so editing them is the quickest way
     * to gain some. The four built-in roles are named in code and cannot be
     * renamed or removed by anyone; their permissions are the super admin's to
     * change. Anyone else may only hand out codes they hold themselves, and
     * never to their own role.
     */
    private function guardRole(UserTypeRequest|Request $request, ?UserType $role): ?JsonResponse
    {
        $actor = auth()->user();
        $builtIn = $role && in_array($role->name, array_column(UserRole::cases(), 'value'), true);

        if ($builtIn && $request->filled('name') && $request->input('name') !== $role->name) {
            return $this->jsonResponse(['message' => 'لا يمكن تغيير اسم دور أساسي في النظام'], 422);
        }
        if ($actor->hasRole(UserRole::SuperAdmin)) {
            return null;
        }
        if ($builtIn) {
            return $this->jsonResponse(['message' => 'الأدوار الأساسية لا يعدّلها إلا المدير العام'], 403);
        }
        if ($role && $role->id === $actor->user_type_id) {
            return $this->jsonResponse(['message' => 'لا يمكنك تعديل صلاحيات دورك'], 403);
        }

        $own = $actor->loadMissing('userType.permissions')->userType->permissions->pluck('id')->all();
        if (array_diff(array_map('intval', $request->input('permission_ids', [])), $own) !== []) {
            return $this->jsonResponse(['message' => 'لا يمكنك منح صلاحية لا تملكها'], 403);
        }

        return null;
    }

    public function update(UserTypeRequest $request, UserType $userType): JsonResponse
    {
        if ($refused = $this->guardRole($request, $userType)) {
            return $refused;
        }

        $userType->update($request->validated());
        $userType->permissions()->sync($request->input('permission_ids', []));

        return $this->jsonResponse($userType->load('permissions'));
    }

    public function destroy(UserType $userType): JsonResponse
    {
        if (in_array($userType->name, array_column(UserRole::cases(), 'value'), true)) {
            return $this->jsonResponse(['message' => 'لا يمكن حذف دور أساسي في النظام'], 422);
        }
        if ($userType->appUsers()->exists()) {
            return $this->jsonResponse(['message' => 'انقل مستخدمي هذا الدور إلى دور آخر قبل حذفه'], 422);
        }

        $userType->delete();

        return $this->jsonResponse(null, 204);
    }
}
