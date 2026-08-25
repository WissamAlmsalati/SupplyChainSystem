<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\OrderStatusLogRequest;
use App\Models\OrderStatusLog;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Admin Orders", description="Admin platform order management")
 */
class OrderStatusLogController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->jsonResponse(OrderStatusLog::with(['order', 'changedBy'])->paginate(15));
    }

    public function store(OrderStatusLogRequest $request): JsonResponse
    {
        $log = OrderStatusLog::create($request->validated());
        return $this->jsonResponse($log->load(['order', 'changedBy']), 201);
    }

    public function show(OrderStatusLog $orderStatusLog): JsonResponse
    {
        return $this->jsonResponse($orderStatusLog->load(['order', 'changedBy']));
    }

    public function update(OrderStatusLogRequest $request, OrderStatusLog $orderStatusLog): JsonResponse
    {
        $orderStatusLog->update($request->validated());
        return $this->jsonResponse($orderStatusLog->load(['order', 'changedBy']));
    }

    public function destroy(OrderStatusLog $orderStatusLog): JsonResponse
    {
        $orderStatusLog->delete();
        return $this->jsonResponse(null, 204);
    }
}
