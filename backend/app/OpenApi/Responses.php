<?php

namespace App\OpenApi;

/**
 * Every failure in this API comes back in one of a handful of shapes, produced
 * by BaseApiController and the handlers in bootstrap/app.php. Defining them
 * once here means an endpoint can say "and it can fail like this" in a line,
 * and the reader sees the actual body rather than a description of one.
 *
 * Use them as: @OA\Response(response=422, ref="#/components/responses/ValidationError")
 *
 * @OA\Schema(schema="Error", type="object",
 *     description="Any 4xx or 5xx. `message` is Arabic and safe to show a user as-is.",
 *
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="غير مصرح")
 * )
 *
 * @OA\Schema(schema="ValidationError", type="object",
 *
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="البيانات المدخلة غير صحيحة"),
 *     @OA\Property(property="errors", type="object", description="Field name to the messages for it.",
 *         example={"amount": {"المبلغ يتجاوز المتبقي على الطلب (40.00 د.ل)"}, "address_id": {"حقل العنوان مطلوب."}})
 * )
 *
 * @OA\Schema(schema="PaginationMeta", type="object",
 *     description="Every list answers with this shape. Endpoints may add their own keys beside it (`summary`, `status_counts`).",
 *
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=58),
 *     @OA\Property(property="last_page", type="integer", example=4),
 *     @OA\Property(property="has_more", type="boolean", example=true)
 * )
 *
 * @OA\Schema(schema="Created", type="object",
 *     description="Shape of a 201.",
 *
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="تم الإنشاء بنجاح"),
 *     @OA\Property(property="data", type="object")
 * )
 *
 * @OA\Response(response="Unauthenticated", description="No token, or the token expired. Sign in again.",
 *
 *     @OA\JsonContent(ref="#/components/schemas/Error",
 *         example={"success": false, "message": "يجب تسجيل الدخول"}))
 *
 * @OA\Response(response="Forbidden", description="Signed in, but this role lacks the permission the route requires.",
 *
 *     @OA\JsonContent(ref="#/components/schemas/Error",
 *         example={"success": false, "message": "غير مصرح"}))
 *
 * @OA\Response(response="NotFound", description="No such row, or it belongs to someone else.",
 *
 *     @OA\JsonContent(ref="#/components/schemas/Error",
 *         example={"success": false, "message": "العنصر غير موجود"}))
 *
 * @OA\Response(response="ValidationError", description="The body did not validate, or a rule of the domain refused it.",
 *
 *     @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 *
 * @OA\Response(response="TooManyRequests", description="Rate limited. `Retry-After` says how many seconds to wait.",
 *
 *     @OA\Header(header="Retry-After", description="Seconds until the next attempt is allowed.", @OA\Schema(type="integer", example=42)),
 *     @OA\Header(header="X-RateLimit-Remaining", description="Attempts left in the current window.", @OA\Schema(type="integer", example=0)),
 *
 *     @OA\JsonContent(ref="#/components/schemas/Error",
 *         example={"success": false, "message": "محاولات كثيرة، حاول مرة أخرى بعد قليل"}))
 */
class Responses
{
    //
}
