<?php

namespace App\OpenApi;

/**
 * The customer app's view of "who am I" and "what was I told". These are the
 * same actions the dashboard reaches at /me, /logout and /notifications, served
 * again under /customer/ so that everything the customer app calls lives under
 * one prefix. They are described here because swagger-php sees one operation
 * per controller action, and that one already belongs to the flat path.
 *
 * @OA\Get(path="/customer/me", tags={"Customer Auth"}, summary="The signed-in customer",
 *     description="Call it once after sign-in and on app start to check the token is still good. `has_addresses` tells the app whether to send a new customer to add a delivery address first.",
 *
 *     @OA\Response(response=200, description="The account with its profile, role and addresses."))
 *
 * @OA\Post(path="/customer/logout", tags={"Customer Auth"}, summary="Sign out",
 *     description="Revokes the token this request was sent with. Other devices stay signed in.",
 *
 *     @OA\Response(response=200, description="Signed out.",
 *
 *         @OA\JsonContent(example={"message": "تم تسجيل الخروج بنجاح"})))
 *
 * @OA\Get(path="/customer/notifications", tags={"Customer Notifications"}, summary="My notifications, newest first",
 *
 *     @OA\Parameter(name="unread_only", in="query", description="1 = only the ones not read yet", @OA\Schema(type="boolean")),
 *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
 *
 *     @OA\Response(response=200, description="`link` is an in-app path to open when the notification is tapped, or null."))
 *
 * @OA\Get(path="/customer/notifications/unread-count", tags={"Customer Notifications"}, summary="How many are unread",
 *     description="Cheap enough to poll for the bell badge.",
 *
 *     @OA\Response(response=200, description="Count.", @OA\JsonContent(example={"count": 3})))
 *
 * @OA\Get(path="/customer/notifications/{id}", tags={"Customer Notifications"}, summary="One notification",
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *
 *     @OA\Response(response=200, description="The notification."),
 *     @OA\Response(response=403, description="It belongs to someone else.",
 *
 *         @OA\JsonContent(ref="#/components/schemas/Error", example={"success": false, "message": "غير مصرح"})))
 *
 * @OA\Patch(path="/customer/notifications/{id}/read", tags={"Customer Notifications"}, summary="Mark one as read",
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *
 *     @OA\Response(response=200, description="Marked. Doing it twice is harmless.", @OA\JsonContent(example={"message": "تم التحديد كمقروء"})),
 *     @OA\Response(response=403, description="It belongs to someone else.",
 *
 *         @OA\JsonContent(ref="#/components/schemas/Error", example={"success": false, "message": "غير مصرح"})))
 *
 * @OA\Patch(path="/customer/notifications/mark-all-read", tags={"Customer Notifications"}, summary="Mark all as read",
 *
 *     @OA\Response(response=200, description="All marked.", @OA\JsonContent(example={"message": "تم تحديد الكل كمقروء"})))
 *
 * @OA\Delete(path="/customer/notifications/{id}", tags={"Customer Notifications"}, summary="Delete one",
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *
 *     @OA\Response(response=204, description="Deleted. No body."),
 *     @OA\Response(response=403, description="It belongs to someone else.",
 *
 *         @OA\JsonContent(ref="#/components/schemas/Error", example={"success": false, "message": "غير مصرح"})))
 */
class CustomerAccount {}
