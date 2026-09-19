<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeliveryFailureReason;
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
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DelegateLocationRequest")),
     *
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
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DelegateAvailabilityRequest")),
     *
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
     *
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *
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
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
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

        // Moves the delegate app may offer next (subset of the order lifecycle).
        $order->setAttribute('next_statuses', array_values(array_intersect(
            $order->status->nextValues(),
            [OrderStatus::OutForDelivery->value, OrderStatus::Delivered->value, OrderStatus::DeliveryFailed->value],
        )));
        // What the driver will be handed at the door, and the reasons to pick from if nobody is there.
        $order->setAttribute('amount_to_collect', max(0, $order->balanceCents()['outstanding']) / 100);
        $order->setAttribute('failure_reasons', DeliveryFailureReason::labels());

        return $this->jsonResponse($order);
    }

    /**
     * @OA\Post(path="/delegate/orders/{id}/status", tags={"Delegate Mobile"}, summary="Move an assigned order: out for delivery, delivered, or delivery failed",
     *     description="`delivery_failed` needs a `reason` (customer_absent, unreachable, wrong_address, refused, other) and, for `other`, a `note`. From there the order can go `out_for_delivery` again for another attempt. The order view returns `next_statuses`, `amount_to_collect` and `failure_reasons` with their Arabic labels.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"status"},
     *
     *         @OA\Property(property="status", type="string", enum={"out_for_delivery", "delivered", "delivery_failed"}),
     *         @OA\Property(property="reason", type="string", enum={"customer_absent", "unreachable", "wrong_address", "refused", "other"}, example="customer_absent"),
     *         @OA\Property(property="note", type="string", nullable=true, example="المحل مقفل"))),
     *
     *     @OA\Response(response=200, description="Order updated"),
     *     @OA\Response(response=422, description="Move not allowed from the current status"))
     */
    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in([OrderStatus::OutForDelivery->value, OrderStatus::Delivered->value, OrderStatus::DeliveryFailed->value])],
            // Why it could not be delivered; "other" has to say what.
            'reason' => ['required_if:status,'.OrderStatus::DeliveryFailed->value, 'nullable', Rule::in(DeliveryFailureReason::values())],
            'note' => ['required_if:reason,'.DeliveryFailureReason::Other->value, 'nullable', 'string', 'max:255'],
        ]);

        $order = Order::where('delegate_id', auth()->id())->findOrFail($id);
        $target = OrderStatus::from($data['status']);
        $wasDelivered = $order->status === OrderStatus::Delivered;

        $changes = ['status' => $target];
        if ($target === OrderStatus::DeliveryFailed) {
            $reason = DeliveryFailureReason::from($data['reason']);
            $changes += ['delivery_failure_reason' => $reason->value, 'delivery_failure_note' => $data['note'] ?? null];
            $order->statusNote = $reason->label().(! empty($data['note']) ? ': '.$data['note'] : '');
        }
        $order->update($changes);

        $collected = ($wasDelivered || $target !== OrderStatus::Delivered) ? 0 : (float) $order->payments()->where('collected_by', auth()->id())->sum('amount');

        return $this->jsonResponse([
            'id' => $order->id,
            'status' => $order->status,
            'cash_collected' => round($collected, 2),
            'custody_balance' => auth()->user()->delegateProfile()->value('custody_balance'),
            'message' => 'تم تحديث حالة الطلب',
        ]);
    }
}
