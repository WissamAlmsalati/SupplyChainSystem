<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AppUserRequest;
use App\Models\AppUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(name="Admin Users", description="Admin platform user management")
 */
class AppUserController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/users",
     *     tags={"Admin Users"},
     *     summary="List app users",
     *     @OA\Response(response=200, description="Paginated list of app users")
     * )
     */
    public function index(): JsonResponse
    {
        return $this->jsonResponse(AppUser::with(['userType', 'cafe'])->paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/users",
     *     tags={"Admin Users"},
     *     summary="Create an app user",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AppUserRequest")),
     *     @OA\Response(response=201, description="App user created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(AppUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password_hash'] = Hash::make($data['password']);
        unset($data['password']);

        $user = AppUser::create($data);
        return $this->jsonResponse($user->load(['userType', 'cafe']), 201);
    }

    /**
     * @OA\Get(
     *     path="/users/{id}",
     *     tags={"Admin Users"},
     *     summary="Get an app user",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="App user details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(AppUser $user): JsonResponse
    {
        return $this->jsonResponse($user->load(['userType', 'cafe', 'orders']));
    }

    /**
     * @OA\Put(
     *     path="/users/{id}",
     *     tags={"Admin Users"},
     *     summary="Update an app user",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AppUserRequest")),
     *     @OA\Response(response=200, description="App user updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(AppUserRequest $request, AppUser $user): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
        }
        unset($data['password']);

        $user->update($data);
        return $this->jsonResponse($user->load(['userType', 'cafe']));
    }

    /**
     * @OA\Delete(
     *     path="/users/{id}",
     *     tags={"Admin Users"},
     *     summary="Delete an app user",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="App user deleted")
     * )
     */
    public function destroy(AppUser $user): JsonResponse
    {
        $user->delete();
        return $this->jsonResponse(null, 204);
    }
}
