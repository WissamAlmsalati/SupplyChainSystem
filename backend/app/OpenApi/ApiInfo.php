<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Al-Sahel Cafe Supplies API",
 *     description="
 * The API behind الساحل لمستلزمات المقاهي: the admin dashboard, the customer app and the delegate app all talk to this one service.
 *
 * ### Authenticating
 *
 * Everything except sign-in, customer registration, password reset, the payment callback and the read-only storefront needs a bearer token. Each app has its own door, because a phone number is only unique within a user type:
 *
 * | App | Sign in at | With |
 * |---|---|---|
 * | Customer | `POST /customer/login` | phone + password |
 * | Delegate | `POST /delegate/login` | phone + password |
 * | Dashboard | `POST /admin/login` | email + password |
 *
 * ```
 * Authorization: Bearer 42|abc…
 * ```
 *
 * Tokens expire (30 days by default). A `401` means sign in again.
 *
 * ### What a response looks like
 *
 * A single record is returned as-is. A list is always `{ data, meta }`, where `meta` carries the paging and may add keys of its own — `summary` on custody, `status_counts` on top-up requests:
 *
 * ```json
 * { ""data"": [ … ], ""meta"": { ""current_page"": 1, ""per_page"": 15, ""total"": 58, ""last_page"": 4, ""has_more"": true } }
 * ```
 *
 * A `201` wraps its result: `{ success, message, data }`.
 *
 * ### When something goes wrong
 *
 * Failures share one shape, and `message` is Arabic that is safe to show a user unchanged:
 *
 * ```json
 * { ""success"": false, ""message"": ""البيانات المدخلة غير صحيحة"", ""errors"": { ""amount"": [""المبلغ يتجاوز المتبقي على الطلب (40.00 د.ل)""] } }
 * ```
 *
 * `401` not signed in · `403` signed in without the permission · `404` missing, or not yours · `422` refused by validation or by a rule of the business · `429` rate limited, with `Retry-After`.
 *
 * Note that `422` is not only about malformed input. An order cannot move to a state its lifecycle forbids, and a payment cannot exceed what the order still owes; both answer `422` with the reason.
 *
 * ### Updating a record
 *
 * Updates are `PATCH`: send the fields you want changed and leave the rest alone. `PUT` is still accepted everywhere `PATCH` is, so older clients keep working, but new code should send `PATCH`.
 *
 * ### Sending a write safely twice
 *
 * A phone on a weak network retries, and people double-tap. Send an `Idempotency-Key` header
 * (any unique string up to 100 characters, a UUID is ideal) with a `POST` that creates an order,
 * a payment, a top-up, a settlement or a return. The first request does the work; a repeat with
 * the same key within 24 hours gets the first answer back, marked `Idempotent-Replay: true`,
 * and nothing is created twice. Make one key per user action and reuse it only when retrying
 * that action. The same key with a different body is answered `422`, and a repeat that arrives
 * while the first is still running is answered `409`. Failed attempts are not remembered, so
 * retrying after an error with the same key is safe. The header is optional.
 *
 * ### Images
 *
 * Wherever the API returns a picture it returns three fields together. `image_url` is always
 * there: a product, category or promo without a picture of its own gets the shared default
 * artwork, so a client never has to handle a missing image. `image_type` is the file format
 * (`svg`, `jpg`, `png`, `webp`, `gif`, `heic`; `jpeg` is reported as `jpg`, and it is `null`
 * only for an external URL that does not say). **Draw by this field**: the default artwork is
 * `svg` and uploads are bitmaps, and most mobile toolkits need a different widget for each.
 * `image_is_placeholder` is `true` while the picture is that default artwork. Product gallery
 * images carry `image_type` too, and a wallet top-up receipt carries `receipt_format`.
 *
 * ### Rate limits
 *
 * Sign-in and the OTP endpoints are limited per IP and per account; everything else shares a general ceiling. A `429` carries `Retry-After` in seconds.
 *
 * ### Searching in Arabic
 *
 * List `search` parameters fold Arabic spelling, so `مصراته` finds `مصراتة`, `بنغازى` finds `بنغازي`, and `١٢٥` finds `125`.
 * ",
 * )
 *
 * @OA\Server(url="/api/v1", description="This server")
 *
 * @OA\Tag(name="Admin Dashboard", description="Admin platform analytics"),
 * @OA\Tag(name="Admin Users", description="Admin platform user management"),
 * @OA\Tag(name="Admin Customers", description="Admin platform customer management"),
 * @OA\Tag(name="Admin Branches", description="Admin platform branch management"),
 * @OA\Tag(name="Admin Orders", description="Admin platform order management"),
 * @OA\Tag(name="Admin Inventory", description="Admin platform inventory management"),
 * @OA\Tag(name="Admin Products", description="Admin platform product management"),
 * @OA\Tag(name="Admin Categories", description="Admin platform category management"),
 * @OA\Tag(name="Admin Warehouses", description="Admin platform warehouse management"),
 * @OA\Tag(name="Admin Delivery Zones", description="Admin platform delivery zone management"),
 * @OA\Tag(name="Admin Roles", description="Admin platform roles and permissions"),
 * @OA\Tag(name="Admin Activity Logs", description="Admin platform activity logs"),
 * @OA\Tag(name="Storefront", description="Public storefront endpoints"),
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class ApiInfo
{
    //
}
