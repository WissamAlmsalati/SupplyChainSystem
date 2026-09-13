<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Auth\CafeRegisterRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Models\AppUser;
use App\Models\Notification;
use App\Models\PasswordResetOtp;
use App\Models\PremiumFeature;
use App\Models\UserType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
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

        $user = AppUser::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'mobile_number' => $request->validated('mobile_number'),
            'password' => Hash::make($request->validated('password')),
            'user_type_id' => $cafeType->id,
            'is_active' => true,
        ]);

        return $this->jsonResponse([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user->load('userType'),
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
     *     summary="Register a cafe account (OTP verification required before login)",
     *     description="Creates an inactive cafe user and sends a 6-digit OTP. Verify it via POST /cafe/verify-otp: when cafe_auto_approve is active a bearer token is returned immediately, otherwise the account waits for admin approval and only a message is returned.",
     *     security={},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CafeRegisterRequest")),
     *     @OA\Response(response=201, description="Account created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function registerCafe(CafeRegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // ponytail: do not create the user row until the OTP is verified.
        // Store the registration data inside the OTP record payload.
        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone_number' => $validated['phone_number'],
            'password' => $validated['password'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ];

        $otp = $this->issueOtp($validated['phone_number'], $payload);

        return $this->jsonResponse([
            'message' => 'تم إرسال رمز التحقق إلى رقم هاتفك',
            'status' => 'otp_sent',
            'token' => $otp['token'],
            'otp' => $otp['otp'], // ponytail: exposed for demo/testing only; remove in production SMS flow
            'user' => [
                'name' => $payload['name'],
                'phone_number' => $payload['phone_number'],
            ],
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/cafe/verify-otp",
     *     tags={"Auth"},
     *     summary="Verify registration OTP",
     *     description="When cafe_auto_approve is active the user is activated and a bearer token is returned immediately. Otherwise the account stays inactive until an admin approves it, and only a message is returned.",
     *     security={},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="token", type="string"),
     *         @OA\Property(property="otp", type="string")
     *     )),
     *     @OA\Response(response=200, description="Verified: token (auto-approve) or pending-approval message")
     * )
     */
    public function verifyRegistrationOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'otp' => ['required', 'string'],
        ]);

        $record = PasswordResetOtp::where('token', hash('sha256', $data['token']))
            ->where('otp', $data['otp'])
            ->first();

        if (! $record || $record->isExpired()) {
            return $this->jsonResponse(['message' => 'رمز التحقق غير صالح أو منتهي الصلاحية'], 422);
        }

        if (empty($record->payload)) {
            return $this->jsonResponse(['message' => 'طلب التسجيل غير مكتمل'], 422);
        }

        $cafeType = UserType::where('name', 'cafe')->firstOrFail();

        $user = AppUser::create([
            'name' => $record->payload['name'],
            'email' => $record->payload['email'] ?? null,
            'mobile_number' => $record->payload['phone_number'],
            'password' => Hash::make($record->payload['password']),
            'user_type_id' => $cafeType->id,
            'is_active' => false,
        ]);

        $user->customerProfile?->update([
            'business_name' => $record->payload['name'],
            'latitude' => $record->payload['latitude'] ?? null,
            'longitude' => $record->payload['longitude'] ?? null,
        ]);

        $record->delete();

        if (PremiumFeature::isActive('cafe_auto_approve')) {
            $user->update(['is_active' => true]);

            return $this->jsonResponse([
                'message' => 'تم تفعيل حسابك بنجاح',
                'status' => 'active',
                'token' => $user->createToken('api')->plainTextToken,
                'user' => $user->only(['id', 'name', 'email', 'mobile_number']),
            ]);
        }

        Notification::notifyAdmins(
            'تسجيل مقهى جديد',
            "مستخدم جديد ({$user->name}) بانتظار الموافقة على تفعيل حسابه",
            '/users',
            'cafe_registration'
        );

        return $this->jsonResponse([
            'message' => 'تم التحقق من رقمك، حسابك قيد مراجعة الإدارة وسيتم تفعيله قريباً',
            'status' => 'pending_approval',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/cafe/resend-otp",
     *     tags={"Auth"},
     *     summary="Resend the registration OTP",
     *     security={},
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="mobile_number", type="string"))),
     *     @OA\Response(response=200, description="OTP resent")
     * )
     */
    public function resendRegistrationOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['mobile_number' => ['required', 'string', 'max:20']]);

        // Pending registration that has not been verified yet.
        $pending = PasswordResetOtp::where('mobile_number', $data['mobile_number'])
            ->whereNotNull('payload')
            ->first();

        if ($pending) {
            $otp = $this->issueOtp($data['mobile_number'], $pending->payload);

            return $this->jsonResponse([
                'message' => 'تم إرسال رمز التحقق',
                'status' => 'otp_sent',
                'token' => $otp['token'],
                'otp' => $otp['otp'], // ponytail: demo only
            ]);
        }

        $user = AppUser::where('mobile_number', $data['mobile_number'])
            ->whereHas('userType', fn ($q) => $q->where('name', 'cafe'))
            ->first();

        if (! $user) {
            return $this->jsonResponse(['message' => 'رقم الهاتف غير مسجل'], 404);
        }

        if ($user->is_active) {
            return $this->jsonResponse(['message' => 'الحساب مفعل بالفعل'], 409);
        }

        return $this->jsonResponse([
            'message' => 'تم التحقق من رقمك مسبقاً، حسابك قيد مراجعة الإدارة',
            'status' => 'pending_approval',
        ]);
    }

    // Replaces any previous OTP for the number; 6 digits, 15 minutes to verify.
    private function issueOtp(string $mobileNumber, ?array $payload = null): array
    {
        PasswordResetOtp::where('mobile_number', $mobileNumber)->delete();

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = Str::random(32);

        PasswordResetOtp::create([
            'mobile_number' => $mobileNumber,
            'token' => hash('sha256', $token),
            'otp' => $otp,
            'payload' => $payload,
            'expires_at' => now()->addMinutes(15),
        ]);

        return ['token' => $token, 'otp' => $otp];
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
        return $this->jsonResponse($request->user()->load(['userType.permissions', 'addresses', 'adminProfile', 'customerProfile', 'delegateProfile']));
    }
}
