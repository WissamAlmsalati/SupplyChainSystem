<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\DelegateLocationUpdated;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(name="Delegate Mobile", description="Endpoints for the delegate mobile app")
 */
class DelegateMobileController extends BaseApiController
{
    protected function isDelegate(): bool
    {
        return auth()->user()?->userType?->name === UserRole::Delegate->value;
    }

    protected function profile()
    {
        $user = auth()->user();

        return $user->delegateProfile ?? $user->delegateProfile()->create();
    }

    /**
     * @OA\Post(path="/delegate/location", tags={"Delegate Mobile"}, summary="Report current location",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DelegateLocationRequest")),
     *     @OA\Response(response=200, description="Location saved"))
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

        $profile = $this->profile();
        $profile->update($data + ['location_updated_at' => now()]);

        broadcast(new DelegateLocationUpdated(auth()->user()->setRelation('delegateProfile', $profile)))->toOthers();

        return $this->jsonResponse([
            'latitude' => $profile->latitude,
            'longitude' => $profile->longitude,
            'location_updated_at' => $profile->location_updated_at,
        ]);
    }

    /**
     * @OA\Post(path="/delegate/availability", tags={"Delegate Mobile"}, summary="Go online/offline",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DelegateAvailabilityRequest")),
     *     @OA\Response(response=200, description="Availability saved"))
     */
    public function setAvailability(Request $request): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $profile = $this->profile();
        $profile->update($data);

        return $this->jsonResponse([
            'is_available' => $profile->is_available,
        ]);
    }

    /**
     * @OA\Get(path="/delegate/orders", tags={"Delegate Mobile"}, summary="Orders assigned to me",
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Orders"))
     */
    public function myOrders(Request $request): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $query = Order::with(['user', 'address', 'deliveryZone', 'items.productVariant'])
            ->where('delegate_id', auth()->id())
            ->orderByDesc('placed_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $this->jsonResponse(['data' => $query->get()]);
    }

    /**
     * @OA\Get(path="/delegate/orders/{id}", tags={"Delegate Mobile"}, summary="Assigned order details",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Order"))
     */
    public function showOrder(int $id): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $order = Order::with(['user', 'address', 'deliveryZone', 'items.productVariant'])
            ->where('delegate_id', auth()->id())
            ->findOrFail($id);

        return $this->jsonResponse($order);
    }

    /**
     * @OA\Post(path="/delegate/orders/{id}/status", tags={"Delegate Mobile"}, summary="Mark an assigned order delivered",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="status", type="string", enum={"delivered"}))),
     *     @OA\Response(response=200, description="Order updated"))
     */
    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $request->validate([
            'status' => ['required', 'string', Rule::in([OrderStatus::Delivered->value])],
        ]);

        $order = Order::where('delegate_id', auth()->id())->findOrFail($id);
        $order->update(['status' => OrderStatus::Delivered]);

        return $this->jsonResponse([
            'id' => $order->id,
            'status' => $order->status,
            'message' => 'تم تحديث حالة الطلب',
        ]);
    }
}
