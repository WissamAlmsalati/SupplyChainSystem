<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Models\AppUser;
use App\Models\Cafe;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(name="Auth", description="Shared authentication endpoints")
 * @OA\Tag(name="Admin Users", description="Admin platform user management")
 */
class AuthController extends BaseApiController
{
    /**
     * @OA\Post(
     *     path="/login",
     *     tags={"Auth"},
     *     summary="Log in and receive an access token",
     *     security={},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AuthLoginRequest")),
     *     @OA\Response(response=200, description="Login successful", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
     *     @OA\Response(response=401, description="Invalid credentials"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = AppUser::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password_hash)) {
            return $this->jsonResponse(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        $user->load(['userType.permissions']);
        $codes = $user->userType?->permissions?->pluck('code') ?? [];

        return $this->jsonResponse([
            'token' => $user->createToken('api')->plainTextToken,
            'permissions' => $codes,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/register",
     *     tags={"Admin Users"},
     *     summary="Register a new cafe user (admin only)",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AuthRegisterRequest")),
     *     @OA\Response(response=201, description="Registration successful", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = auth()->user();
        if ($user && ! in_array($user->userType?->name, ['admin', 'super_admin'], true)) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $cafeType = UserType::where('name', 'cafe')->firstOrFail();

        $cafeId = $request->validated('cafe_id');
        if (! $cafeId) {
            $cafe = Cafe::create([
                'name' => $request->validated('name'),
                'contact_info' => $request->validated('mobile_number'),
                'created_by_admin_id' => auth()->id(),
                'is_active' => true,
            ]);
            $cafeId = $cafe->id;
        }

        $user = AppUser::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'mobile_number' => $request->validated('mobile_number'),
            'password_hash' => Hash::make($request->validated('password')),
            'user_type_id' => $cafeType->id,
            'cafe_id' => $cafeId,
            'is_active' => true,
        ]);

        return $this->jsonResponse([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user->load('cafe'),
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/logout",
     *     tags={"Auth"},
     *     summary="Revoke the current access token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logged out successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->jsonResponse(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    /**
     * @OA\Get(
     *     path="/me",
     *     tags={"Auth"},
     *     summary="Get the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Authenticated user details"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        return $this->jsonResponse($request->user()->load(['userType.permissions', 'cafe']));
    }
}
