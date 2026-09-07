<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Admin Activity Logs", description="Admin platform activity logs")
 */
class ActivityLogController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%")
                  ->orWhere('entity_type', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);
        return $this->jsonResponse($query->orderByDesc('id')->paginate($perPage > 0 ? min($perPage, 10000) : 15));
    }
}
