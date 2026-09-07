<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Auth\CafeRegisterRequest;
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
     *     description="Cafe users must login with phone_number and password. The response is a bare bearer token with no permissions. Admin and delegate users may use email instead of phone_number and will receive permissions in the response.",
     *     security={},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AuthLoginRequest",
     *             example={"phone_number": "0912345678", "password": "password"}
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(ref="#/components/schemas/AuthResponse", example={"token": "1|laravel_sanctum_bearer_token_here"})
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $phone = $request->validated('phone_number');

        $user = AppUser::query()
            ->when(
                $email,
                fn ($q, $email) => $q->where('email', $email),
                fn ($q) => $q->where('mobile_number', $phone)
            )
            ->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password_hash)) {
            return $this->jsonResponse(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        if ($user->userType?->name === 'cafe' && $email) {
            return $this->jsonResponse(['message' => 'يجب تسجيل الدخول برقم الهاتف'], 403);
        }

        if (! $user->is_active) {
            return $this->jsonResponse(['message' => 'الحساب غير نشط، يرجى انتظار موافقة الإدارة'], 403);
        }

        $user->load(['userType.permissions']);
        $codes = $user->userType?->permissions?->pluck('code') ?? [];

        $response = [
            'token' => $user->createToken('api')->plainTextToken,
        ];

        // ponytail: hide permissions for cafe users for now without deleting the loading code
        if ($user->userType?->name !== 'cafe') {
            $response['permissions'] = $codes;
        } else {
            $response['has_cafe'] = $user->cafeUser()->exists();
        }

        return $this->jsonResponse($response);
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
            'is_active' => true,
        ]);
        $user->syncCafeUser(['cafe_id' => $cafeId]);

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
    /**
     * @OA\Post(
     *     path="/cafe/register",
     *     tags={"Auth"},
     *     summary="Register a cafe account (user only, cafe is added after login)",
     *     description="Creates an active cafe-type user with no cafe attached. After logging in, call GET /cafe/profile to check has_cafe, then POST /cafe/profile to add the cafe.",
     *     security={},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CafeRegisterRequest")),
     *     @OA\Response(response=201, description="Account created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function registerCafe(CafeRegisterRequest $request): JsonResponse
    {
        $cafeType = UserType::where('name', 'cafe')->firstOrFail();

        $user = AppUser::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'mobile_number' => $request->validated('phone_number'),
            'password_hash' => Hash::make($request->validated('password')),
            'user_type_id' => $cafeType->id,
            'is_active' => true,
        ]);

        return $this->jsonResponse([
            'message' => 'تم إنشاء الحساب بنجاح، يمكنك تسجيل الدخول الآن',
            'user' => [
                'name' => $user->name,
                'phone_number' => $user->mobile_number,
            ],
        ], 201);
    }

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
