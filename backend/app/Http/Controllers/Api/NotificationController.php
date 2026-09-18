<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use Illuminate\Validation\Rule;
use App\Models\AppUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return $this->paginated($query->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    /**
     * @OA\Get(
     *     path="/notifications/unread-count",
     *     tags={"Notifications"},
     *     summary="Get unread notification count",
     *     @OA\Response(response=200, description="Unread count")
     * )
     */
    public const AUDIENCES = ['all', 'customers', 'delegates', 'admins', 'users'];

    /**
     * @OA\Post(path="/notifications/send", tags={"Notifications"}, summary="Send an announcement to an audience", security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"audience","title"},
     *         @OA\Property(property="audience", type="string", enum={"all","customers","delegates","admins","users"}),
     *         @OA\Property(property="user_ids", type="array", @OA\Items(type="integer"), description="Required when audience=users"),
     *         @OA\Property(property="title", type="string", maxLength=150),
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="link", type="string", maxLength=255))),
     *     @OA\Response(response=201, description="Sent; data.sent is the recipient count"))
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(self::AUDIENCES)],
            'user_ids' => ['required_if:audience,users', 'array', 'max:500'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:2000'],
            'link' => ['nullable', 'string', 'max:255'],
        ]);

        $types = match ($data['audience']) {
            'customers' => ['customer'],
            'delegates' => ['delegate'],
            'admins' => ['admin', 'super_admin'],
            default => null,
        };

        $ids = $data['audience'] === 'users'
            ? collect($data['user_ids'])
            : AppUser::query()->where('is_active', true)
                ->when($types, fn ($q) => $q->whereHas('userType', fn ($t) => $t->whereIn('name', $types)))
                ->pluck('id');

        if ($ids->isEmpty()) {
            return $this->jsonResponse(['message' => 'لا يوجد مستلمون لهذه الفئة'], 422);
        }

        $sent = Notification::sendTo($ids, $data['title'], $data['message'] ?? null, $data['link'] ?? null, 'announcement');

        return $this->jsonResponse([
            'message' => "أُرسل الإشعار إلى {$sent} مستخدم",
            'data' => ['sent' => $sent, 'audience' => $data['audience']],
        ], 201);
    }

    /**
     * @OA\Get(path="/notifications/sent", tags={"Notifications"}, summary="Announcements sent from the dashboard, newest first", security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Batches with recipient and read counts"))
     */
    public function sent(Request $request): JsonResponse
    {
        $batches = Notification::query()->where('type', 'announcement')
            ->selectRaw('title, message, link, created_at, COUNT(*) as recipients, SUM(CASE WHEN read_at IS NULL THEN 0 ELSE 1 END) as read_count')
            ->groupBy('title', 'message', 'link', 'created_at')
            ->orderByDesc('created_at')
            ->limit($request->integer('limit', 30))
            ->get()
            ->map(fn ($b) => [
                'title' => $b->title,
                'message' => $b->message,
                'link' => $b->link,
                'sent_at' => $b->created_at?->toDateTimeString(),
                'recipients' => (int) $b->recipients,
                'read' => (int) $b->read_count,
            ]);

        return $this->jsonResponse(['data' => $batches]);
    }

    public function unreadCount(): JsonResponse
    {
        $count = Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return $this->jsonResponse(['count' => $count]);
    }

    /**
     * @OA\Get(
     *     path="/notifications/{id}",
     *     tags={"Notifications"},
     *     summary="Get a single notification",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notification details")
     * )
     */
    public function show(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        return $this->jsonResponse($notification);
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
