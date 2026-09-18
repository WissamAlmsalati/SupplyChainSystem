<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PermissionRequest;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Admin Roles", description="Admin platform roles and permissions")
 */
class PermissionController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->jsonResponse(Permission::all());
    }

    public function store(PermissionRequest $request): JsonResponse
    {
        return $this->jsonResponse(Permission::create($request->validated()), 201);
    }

    public function show(Permission $permission): JsonResponse
    {
        return $this->jsonResponse($permission->load('userTypes'));
    }

    public function update(PermissionRequest $request, Permission $permission): JsonResponse
    {
        $permission->update($request->validated());

        return $this->jsonResponse($permission);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return $this->jsonResponse(null, 204);
    }
}
