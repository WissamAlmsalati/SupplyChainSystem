<?php

namespace App\Http\Controllers\Api;

use App\Events\DelegateLocationUpdated;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Delegate Mobile", description="Delegate mobile app endpoints")
 */
class DelegateMobileController extends BaseApiController
{
    protected function isDelegate(): bool
    {
        return auth()->user()?->userType?->name === 'delegate';
    }

    /**
     * @OA\Post(
     *     path="/delegate/location",
     *     tags={"Delegate Mobile"},
     *     summary="Update delegate live location",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DelegateLocationRequest")),
     *     @OA\Response(response=200, description="Location updated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function updateLocation(Request $request): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $user = auth()->user();
        $user->update([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'location_updated_at' => now(),
        ]);

        broadcast(new DelegateLocationUpdated($user))->toOthers();

        return $this->jsonResponse([
            'latitude' => $user->latitude,
            'longitude' => $user->longitude,
            'location_updated_at' => $user->location_updated_at,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/delegate/availability",
     *     tags={"Delegate Mobile"},
     *     summary="Toggle delegate availability",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DelegateAvailabilityRequest")),
     *     @OA\Response(response=200, description="Availability updated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function setAvailability(Request $request): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $user = auth()->user();
        $user->update(['is_available' => $data['is_available']]);

        return $this->jsonResponse([
            'is_available' => $user->is_available,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/delegate/orders",
     *     tags={"Delegate Mobile"},
     *     summary="List assigned orders for the delegate",
     *     @OA\Response(response=200, description="List of orders"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function myOrders(Request $request): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $query = Order::with(['user', 'branch', 'deliveryZone', 'items.productVariant'])
            ->where('delegate_id', auth()->id())
            ->orderByDesc('order_date');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $this->jsonResponse(['data' => $query->get()]);
    }

    /**
     * @OA\Get(
     *     path="/delegate/orders/{id}",
     *     tags={"Delegate Mobile"},
     *     summary="Get assigned order details",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Order details"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function showOrder(int $id): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $order = Order::with(['user', 'branch', 'deliveryZone', 'items.productVariant'])
            ->where('delegate_id', auth()->id())
            ->findOrFail($id);

        return $this->jsonResponse($order);
    }

    /**
     * @OA\Post(
     *     path="/delegate/orders/{id}/status",
     *     tags={"Delegate Mobile"},
     *     summary="Update status of an assigned order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="status", type="string"))),
     *     @OA\Response(response=200, description="Status updated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,processing,completed,delivered,cancelled,failed'],
        ]);

        $order = Order::where('delegate_id', auth()->id())->findOrFail($id);
        $order->update(['status' => $data['status']]);

        return $this->jsonResponse([
            'id' => $order->id,
            'status' => $order->status,
            'message' => 'تم تحديث حالة الطلب',
        ]);
    }
}
