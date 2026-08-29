<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Notifications", description="User notifications")
 */
class NotificationController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/notifications",
     *     tags={"Notifications"},
     *     summary="List current user notifications",
     *     @OA\Parameter(name="unread_only", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="List of notifications")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', auth()->id())
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        return $this->jsonResponse($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * @OA\Get(
     *     path="/notifications/unread-count",
     *     tags={"Notifications"},
     *     summary="Get unread notification count",
     *     @OA\Response(response=200, description="Unread count")
     * )
     */
    public function unreadCount(): JsonResponse
    {
        $count = Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return $this->jsonResponse(['count' => $count]);
    }

    /**
     * @OA\Put(
     *     path="/notifications/{id}/read",
     *     tags={"Notifications"},
     *     summary="Mark a notification as read",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Marked as read")
     * )
     */
    public function markRead(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $notification->markAsRead();
        return $this->jsonResponse(['message' => 'تم التحديد كمقروء']);
    }

    /**
     * @OA\Put(
     *     path="/notifications/mark-all-read",
     *     tags={"Notifications"},
     *     summary="Mark all notifications as read",
     *     @OA\Response(response=200, description="All marked as read")
     * )
     */
    public function markAllRead(): JsonResponse
    {
        Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->jsonResponse(['message' => 'تم تحديد الكل كمقروء']);
    }

    /**
     * @OA\Delete(
     *     path="/notifications/{id}",
     *     tags={"Notifications"},
     *     summary="Delete a notification",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Deleted")
     * )
     */
    public function destroy(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $notification->delete();
        return $this->jsonResponse(null, 204);
    }
}
