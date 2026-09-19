<?php

namespace App\Http\Controllers\Api;

use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(name="Devices", description="Where to push notifications to: the FCM token of each installed app")
 */
class DeviceController extends BaseApiController
{
    /**
     * @OA\Post(path="/customer/devices", tags={"Customer Notifications"}, summary="Register this phone for push notifications",
     *     description="Send the FCM registration token after sign-in, and again whenever Firebase rotates it (`onTokenRefresh`). Calling it again with the same token only refreshes `last_seen_at`. A token belongs to one install, so if it was registered under another account on the same phone it moves to the signed-in one. The same route exists at `/delegate/devices` and `/devices`.",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"token","platform"},
     *
     *         @OA\Property(property="token", type="string", example="fMEP0vJqS0:APA91bHun4MxP5egoKMwt2KZFBaFUH-1RYqx..."),
     *         @OA\Property(property="platform", type="string", enum={"android","ios","web"}, example="android"),
     *         @OA\Property(property="device_name", type="string", nullable=true, example="Samsung A54")
     *     )),
     *
     *     @OA\Response(response=200, description="Registered",
     *
     *         @OA\JsonContent(example={"message": "تم تسجيل الجهاز", "device": {"id": 7, "platform": "android", "app": "customer", "device_name": "Samsung A54", "last_seen_at": "2026-09-19T09:12:41.000000Z"}}))
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['required', Rule::in(DeviceToken::PLATFORMS)],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        // The app is the door the request came through, not something the client claims.
        $app = match (true) {
            $request->routeIs('customer.*') => 'customer',
            $request->routeIs('delegate.*') => 'delegate',
            default => 'admin',
        };

        $device = DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'], 'app' => $app, 'device_name' => $data['device_name'] ?? null, 'last_seen_at' => now()],
        );

        return $this->jsonResponse(['message' => 'تم تسجيل الجهاز', 'device' => $device]);
    }

    /**
     * @OA\Delete(path="/customer/devices", tags={"Customer Notifications"}, summary="Stop pushing to this phone",
     *     description="Call it on sign-out, before the token is dropped, so the next person to use the phone does not receive this account's notifications. Sending `device_token` to the logout route does the same in one call.",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"token"}, @OA\Property(property="token", type="string"))),
     *
     *     @OA\Response(response=200, description="Removed (or was not registered)", @OA\JsonContent(example={"message": "تم إلغاء تسجيل الجهاز"}))
     * )
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);

        DeviceToken::where('user_id', $request->user()->id)->where('token', $data['token'])->delete();

        return $this->jsonResponse(['message' => 'تم إلغاء تسجيل الجهاز']);
    }
}
