<?php

namespace App\Http\Controllers\Api;

use App\Models\AppUser;
use App\Models\PasswordResetOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends BaseApiController
{
    /**
     * @OA\Post(
     *     path="/customer/forgot-password",
     *     tags={"Auth"},
     *     summary="Request password reset OTP for customer user",
     *     security={},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="mobile_number", type="string"))),
     *
     *     @OA\Response(response=200, description="OTP sent"),
     *     @OA\Response(response=404, description="Mobile number not found")
     * )
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile_number' => ['required', 'string', 'max:20'],
        ]);

        $user = AppUser::where('mobile_number', $data['mobile_number'])
            ->whereHas('userType', fn ($q) => $q->where('name', 'customer'))
            ->first();

        if (! $user) {
            return $this->jsonResponse(['message' => 'رقم الهاتف غير مسجل'], 404);
        }

        // ponytail: invalidate previous tokens for this number
        PasswordResetOtp::where('mobile_number', $data['mobile_number'])->delete();

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = Str::random(32);

        PasswordResetOtp::create([
            'mobile_number' => $data['mobile_number'],
            'token' => hash('sha256', $token),
            'otp' => $otp,
            'expires_at' => now()->addMinutes(15),
        ]);

        // ponytail: in production, send OTP via SMS here
        return $this->jsonResponse([
            'message' => 'تم إرسال رمز التحقق',
            'status' => 'otp_sent',
            'token' => $token,
            'otp' => $otp, // ponytail: exposed for demo/testing only; remove in production SMS flow
        ]);
    }

    /**
     * @OA\Post(
     *     path="/customer/reset-password",
     *     tags={"Auth"},
     *     summary="Reset customer user password with OTP",
     *     security={},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="token", type="string"),
     *         @OA\Property(property="otp", type="string"),
     *         @OA\Property(property="password", type="string"),
     *         @OA\Property(property="password_confirmation", type="string")
     *     )),
     *
     *     @OA\Response(response=200, description="Password reset successfully"),
     *     @OA\Response(response=422, description="Invalid or expired token/OTP")
     * )
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'otp' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $record = PasswordResetOtp::where('token', hash('sha256', $data['token']))
            ->where('otp', $data['otp'])
            ->first();

        if (! $record || $record->isExpired()) {
            return $this->jsonResponse(['message' => 'رمز التحقق غير صالح أو منتهي الصلاحية'], 422);
        }

        $user = AppUser::where('mobile_number', $record->mobile_number)
            ->whereHas('userType', fn ($q) => $q->where('name', 'customer'))
            ->first();

        if (! $user) {
            return $this->jsonResponse(['message' => 'المستخدم غير موجود'], 404);
        }

        $user->update(['password' => Hash::make($data['password'])]);
        $record->delete();

        return $this->jsonResponse([
            'message' => 'تم إعادة تعيين كلمة المرور بنجاح',
        ]);
    }
}
