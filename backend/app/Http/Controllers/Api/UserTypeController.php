<?php

namespace App\Http\Controllers\Api;

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

        $userType = UserType::create($request->validated());
        $userType->permissions()->sync($request->input('permission_ids', []));
        return $this->jsonResponse($userType->load('permissions'), 201);
    }

    public function show(UserType $userType): JsonResponse
    {
        return $this->jsonResponse($userType->load(['permissions', 'appUsers']));
    }

    public function update(UserTypeRequest $request, UserType $userType): JsonResponse
    {
        $userType->update($request->validated());
        $userType->permissions()->sync($request->input('permission_ids', []));
        return $this->jsonResponse($userType->load('permissions'));
    }

    public function destroy(UserType $userType): JsonResponse
    {
        $userType->delete();
        return $this->jsonResponse(null, 204);
    }
}
