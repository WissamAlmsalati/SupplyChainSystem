<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Auth\CustomerRegisterRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\PasswordResetOtp;
use App\Models\PremiumFeature;
use App\Models\UserType;
use App\Services\AddressZoneResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @OA\Tag(name="Admin Users", description="Admin platform user management")
 */
class AuthController extends BaseApiController
{
    /**
     * @OA\Post(
     *     path="/login",
     *     tags={"Auth"},
     *     summary="Log in and receive an access token (staff)",
     *     description="Each app has its own door: `/customer/login`, `/delegate/login` and `/admin/login`. Each looks the account up within its own user type, because a phone number is only unique per type — the same number may belong to a customer and to a delegate. This unscoped route remains for clients not yet moved over.",
     *     security={},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AuthLoginRequest",
     *             example={"phone_number": "0912345678", "password": "password"}
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Signed in. Send the token as `Authorization: Bearer …` from here on.",
     *
     *         @OA\JsonContent(ref="#/components/schemas/AuthResponse",
     *             example={"token": "42|8PlDIsOOdQ1Rs0KyzWfyzqnUZhL7j49eoerVoK", "permissions": {"DASHBOARD_VIEW", "ORDERS_VIEW", "ORDERS_CREATE", "REPORTS_VIEW"}})),
     *
     *     @OA\Response(response=401, description="Wrong credentials, or the account is not active yet.",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Error",
     *             example={"success": false, "message": "بيانات الدخول غير صحيحة"})),
     *
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $phone = $request->validated('phone_number');

        // Each app signs in at its own route, and the route says which audience
        // it serves. That matters because a phone number is only unique within
        // a user type: the same number can belong to a customer and to a
        // delegate, and looking it up without the type would be a coin toss.
        $roles = $request->route()?->defaults['roles'] ?? null;

        $user = AppUser::query()
            ->when($roles, fn ($q) => $q->whereHas('userType', fn ($t) => $t->whereIn('name', $roles)))
            ->when(
                $email,
                fn ($q, $email) => $q->where('email', $email),
                fn ($q) => $q->where('mobile_number', $phone)
            )
            ->first();

        // Deliberately the same answer whether the account does not exist or
        // belongs to another app: otherwise this endpoint would confirm which
        // numbers are registered as delegates.
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return $this->jsonResponse(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        if ($user->userType?->name === 'customer' && $email) {
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

        // ponytail: hide permissions for customer users for now without deleting the loading code
        if ($user->userType?->name !== 'customer') {
            $response['permissions'] = $codes;
        }

        return $this->jsonResponse($response);
    }

    /**
     * @OA\Post(
     *     path="/register",
     *     tags={"Admin Users"},
     *     summary="Register a new customer user (admin only)",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AuthRegisterRequest")),
     *
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

        $customerType = UserType::where('name', 'customer')->firstOrFail();

        $user = AppUser::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'mobile_number' => $request->validated('mobile_number'),
            'password' => Hash::make($request->validated('password')),
            'user_type_id' => $customerType->id,
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
     *
     *     @OA\Response(response=200, description="Logged out successfully"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    /**
     * @OA\Post(
     *     path="/customer/register",
     *     tags={"Customer Auth"},
     *     summary="Register a customer account (OTP verification required before login)",
     *     description="Creates an inactive customer user and sends a 6-digit OTP. Verify it via POST /customer/verify-otp: when customer_auto_approve is active a bearer token is returned immediately, otherwise the account waits for admin approval and only a message is returned.",
     *     security={},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CustomerRegisterRequest")),
     *
     *     @OA\Response(response=201, description="Account created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function registerCustomer(CustomerRegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // ponytail: do not create the user row until the OTP is verified.
        // Store the registration data inside the OTP record payload.
        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone_number' => $validated['phone_number'],
            // Hashed before it is stored: this row waits for the OTP, and a
            // password must not sit in the database in the clear meanwhile.
            'password' => Hash::make($validated['password']),
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
     *     path="/customer/verify-otp",
     *     tags={"Customer Auth"},
     *     summary="Verify registration OTP",
     *     description="When customer_auto_approve is active the user is activated and a bearer token is returned immediately. Otherwise the account stays inactive until an admin approves it, and only a message is returned.",
     *     security={},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="token", type="string"),
     *         @OA\Property(property="otp", type="string")
     *     )),
     *
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

        $customerType = UserType::where('name', 'customer')->firstOrFail();

        $user = AppUser::create([
            'name' => $record->payload['name'],
            'email' => $record->payload['email'] ?? null,
            'mobile_number' => $record->payload['phone_number'],
            // Records issued before passwords were hashed at registration still hold the plain one.
            'password' => Hash::isHashed($record->payload['password']) ? $record->payload['password'] : Hash::make($record->payload['password']),
            'user_type_id' => $customerType->id,
            'is_active' => false,
        ]);

        $user->customerProfile?->update([
            'business_name' => $record->payload['name'],
            'latitude' => $record->payload['latitude'] ?? null,
            'longitude' => $record->payload['longitude'] ?? null,
        ]);

        // The place they pinned while registering is where they want deliveries:
        // make it their first address, so a new cafe can order without first
        // discovering that it has to add one. It is kept even when no zone covers
        // it yet: it cannot be ordered to, and the cafe is told the day one does.
        if (($record->payload['latitude'] ?? null) !== null && ($record->payload['longitude'] ?? null) !== null) {
            $zone = rescue(fn () => app(AddressZoneResolver::class)->resolve((float) $record->payload['latitude'], (float) $record->payload['longitude']), null, false);
            Address::create([
                'user_id' => $user->id,
                'name' => $record->payload['name'],
                'latitude' => $record->payload['latitude'],
                'longitude' => $record->payload['longitude'],
                'delivery_zone_id' => $zone?->id,
                'contact_phones' => [$record->payload['phone_number']],
            ]);
        }

        $record->delete();

        if (PremiumFeature::isActive('customer_auto_approve')) {
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
            'customer_registration'
        );

        return $this->jsonResponse([
            'message' => 'تم التحقق من رقمك، حسابك قيد مراجعة الإدارة وسيتم تفعيله قريباً',
            'status' => 'pending_approval',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/customer/resend-otp",
     *     tags={"Customer Auth"},
     *     summary="Resend the registration OTP",
     *     security={},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="mobile_number", type="string"))),
     *
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
            ->whereHas('userType', fn ($q) => $q->where('name', 'customer'))
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
        // Stop pushing to this phone too, or its next user gets this account's notifications.
        if ($request->filled('device_token')) {
            DeviceToken::where('user_id', $request->user()->id)->where('token', (string) $request->input('device_token'))->delete();
        }

        $request->user()->currentAccessToken()->delete();

        return $this->jsonResponse(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    /**
     * @OA\Get(
     *     path="/me",
     *     tags={"Auth"},
     *     summary="Get the authenticated user",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Authenticated user details. has_addresses is true when the user has at least one address (branch); addresses_count gives the number."),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['userType.permissions', 'addresses', 'adminProfile', 'customerProfile', 'delegateProfile']);
        $user->setAttribute('has_addresses', $user->addresses->isNotEmpty());
        $user->setAttribute('addresses_count', $user->addresses->count());

        return $this->jsonResponse($user);
    }
}
