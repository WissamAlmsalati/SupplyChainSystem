<?php

namespace App\OpenApi;

/**
 * The three sign-in routes share one controller action, so swagger-php sees a
 * single operation there. Each door is described here instead, because a
 * reader needs to know which one their app uses — and that using the wrong one
 * is refused even with the right password.
 *
 * @OA\Post(path="/customer/login", tags={"Customer Auth"}, summary="Customer sign-in", security={},
 *     description="Looks the number up among customers only. The same number may also exist as a delegate; that account cannot sign in here.",
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"phone_number","password"},
 *         @OA\Property(property="phone_number", type="string", example="0912345678"),
 *         @OA\Property(property="password", type="string", format="password", example="secret123"))),
 *     @OA\Response(response=200, description="Signed in.",
 *         @OA\JsonContent(@OA\Property(property="token", type="string", example="42|8PlDIsOOdQ1Rs0KyzWfyzqnUZhL7j49eoerVoK"))),
 *     @OA\Response(response=401, description="Wrong password, or the number is not a customer. The wording is the same either way, so the endpoint cannot be used to discover which numbers are registered.",
 *         @OA\JsonContent(ref="#/components/schemas/Error", example={"success": false, "message": "بيانات الدخول غير صحيحة"})),
 *     @OA\Response(response=403, description="The account exists but is awaiting approval.",
 *         @OA\JsonContent(ref="#/components/schemas/Error", example={"success": false, "message": "الحساب غير نشط، يرجى انتظار موافقة الإدارة"})),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
 *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests"))
 *
 * @OA\Post(path="/delegate/login", tags={"Auth"}, summary="Delegate sign-in", security={},
 *     description="Looks the number up among delegates only.",
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"phone_number","password"},
 *         @OA\Property(property="phone_number", type="string", example="0920000001"),
 *         @OA\Property(property="password", type="string", format="password"))),
 *     @OA\Response(response=200, description="Signed in.",
 *         @OA\JsonContent(@OA\Property(property="token", type="string"),
 *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"ORDERS_VIEW", "ORDER_ASSIGN"}))),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
 *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests"))
 *
 * @OA\Post(path="/admin/login", tags={"Auth"}, summary="Dashboard sign-in", security={},
 *     description="Email and password, looked up among admins and super admins. The response carries the permission codes the dashboard uses to decide what to show.",
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"email","password"},
 *         @OA\Property(property="email", type="string", format="email", example="admin@example.com"),
 *         @OA\Property(property="password", type="string", format="password"))),
 *     @OA\Response(response=200, description="Signed in.",
 *         @OA\JsonContent(@OA\Property(property="token", type="string"),
 *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"),
 *                 example={"DASHBOARD_VIEW", "ORDERS_VIEW", "REPORTS_VIEW", "NOTIFICATIONS_SEND"}))),
 *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
 *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
 *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests"))
 */
class LoginDoors
{
    //
}
