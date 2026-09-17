<?php

namespace App\Http\Controllers\Api;

use App\Models\OrderStatusLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Read-only; logs are written automatically when an order's status changes.
class OrderStatusLogController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = OrderStatusLog::with(['order', 'changedBy']);

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->integer('order_id'));
        }

        return $this->paginated($query->orderByDesc('id')->paginate(15));
    }

    public function show(OrderStatusLog $orderStatusLog): JsonResponse
    {
        return $this->jsonResponse($orderStatusLog->load(['order', 'changedBy']));
    }
}
