<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Http\Requests\Api\OrderRequest;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\Order;
use App\Services\OrderPlacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Orders", description="Dashboard order management")
 */
class OrderController extends BaseApiController
{
    protected function isCustomer(): bool
    {
        return auth()->user()?->userType?->name === UserRole::Customer->value;
    }

    protected function orderQuery()
    {
        $query = Order::with(['user', 'address', 'deliveryZone', 'delegate']);

        if ($this->isCustomer()) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }

    protected function canAccessOrder(Order $order): bool
    {
        return ! $this->isCustomer() || $order->user_id === auth()->id();
    }

    // Statuses the dashboard may move this order to next; drives the status UI.
    protected function withNextStatuses(Order $order): Order
    {
        $next = $order->status->nextValues();
        // The model refuses to cancel an order that has a return, so do not offer it.
        if ($order->returns()->exists()) {
            $next = array_values(array_diff($next, [\App\Enums\OrderStatus::Cancelled->value]));
        }
        $order->setAttribute('next_statuses', $next);

        return $order;
    }

    protected function activeDelegate(int $id): ?AppUser
    {
        return AppUser::whereKey($id)
            ->whereHas('userType', fn ($q) => $q->where('name', UserRole::Delegate->value))
            ->where('is_active', true)
            ->first();
    }

    /**
     * @OA\Get(path="/orders", tags={"Orders"}, summary="List orders",
     *
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Paginated orders"))
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->orderQuery()->orderByDesc('placed_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('status', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhere('order_number', 'like', "%{$search}%")
                    ->orWhere('delivery_address_name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status') && in_array($request->input('status'), OrderStatus::values(), true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('placed_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('placed_at', '<=', $request->input('date_to'));
        }

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * @OA\Post(path="/orders", tags={"Orders"}, summary="Create an order for a customer",
     *     description="Prices come from the variants; stock is deducted.",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/OrderRequest")),
     *
     *     @OA\Response(response=201, description="Order created"),
     *     @OA\Response(response=409, description="Insufficient stock"))
     */
    public function store(OrderRequest $request, OrderPlacementService $placement): JsonResponse
    {
        $data = $request->validated();

        if ($this->isCustomer() && (int) $data['user_id'] !== auth()->id()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $customer = AppUser::findOrFail($data['user_id']);
        $address = Address::where('user_id', $customer->id)->find($data['address_id']);
        if (! $address) {
            return $this->jsonResponse(['message' => 'العنوان لا يخص هذا الزبون'], 422);
        }

        $delegateId = null;
        if (! empty($data['delegate_id'])) {
            $delegateId = $this->activeDelegate($data['delegate_id'])?->id;
            if (! $delegateId) {
                return $this->jsonResponse(['message' => 'المندوب غير موجود أو غير نشط'], 422);
            }
        }

        $source = $this->isCustomer() ? OrderSource::App : OrderSource::Dashboard;
        $order = $placement->place($customer, $address, $data['items'], $source, null, $delegateId, PaymentMethod::from($data['payment_method'] ?? 'cash'));

        return $this->jsonResponse($order->load(['user', 'address', 'deliveryZone', 'delegate', 'items.productVariant']), 201);
    }

    /**
     * @OA\Get(path="/orders/{id}", tags={"Orders"}, summary="Order details",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Order"))
     */
    public function show(Order $order): JsonResponse
    {
        if (! $this->canAccessOrder($order)) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $order->load([
            'user.customerProfile', 'user.wallet', 'address', 'deliveryZone', 'delegate', 'cart',
            'items.productVariant.product', 'payments.collector:id,name', 'statusLogs.changedBy',
            'returns.items', 'returns.createdBy:id,name',
        ]);
        $order->items->loadSum('returnItems as returned_quantity', 'quantity');

        // The money position in one block, so the page does not redo the sums.
        $order->setAttribute('balance', array_map(fn (int $c) => $c / 100, $order->balanceCents()));

        return $this->jsonResponse($this->withNextStatuses($order));
    }

    /**
     * @OA\Put(path="/orders/{id}", tags={"Orders"}, summary="Change order status or delegate",
     *     description="Setting status to cancelled returns the order's items to stock.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="status", type="string"),
     *         @OA\Property(property="delegate_id", type="integer", nullable=true))),
     *
     *     @OA\Response(response=200, description="Updated. `next_statuses` lists where it may go from here.",
     *
     *         @OA\JsonContent(example={"id": 58, "order_number": "ORD-2026-09-18-14-003", "status": "confirmed", "next_statuses": {"preparing", "out_for_delivery", "cancelled"}})),
     *
     *     @OA\Response(response=422, description="The lifecycle forbids that move — delivered never returns to pending, and received or cancelled are final.",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError",
     *             example={"success": false, "message": "البيانات المدخلة غير صحيحة", "errors": {"status": {"لا يمكن نقل الطلب من «تم التوصيل» إلى «قيد الانتظار»"}}})),
     *
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"))
     */
    public function update(OrderRequest $request, Order $order): JsonResponse
    {
        // Customers follow their orders through the /customer endpoints.
        if ($this->isCustomer()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validated();

        if (array_key_exists('delegate_id', $data) && $data['delegate_id'] !== null && ! $this->activeDelegate($data['delegate_id'])) {
            return $this->jsonResponse(['message' => 'المندوب غير موجود أو غير نشط'], 422);
        }

        // Illegal moves (e.g. delivered back to pending) are refused by the Order model.
        $order->update(collect($data)->only(['status', 'delegate_id'])->all());

        return $this->jsonResponse($this->withNextStatuses($order->load(['user', 'address', 'deliveryZone', 'delegate', 'items.productVariant', 'statusLogs.changedBy'])));
    }

    /**
     * @OA\Delete(path="/orders/{id}", tags={"Orders"}, summary="Delete a cancelled order",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=204, description="Order deleted"),
     *     @OA\Response(response=422, description="Only cancelled orders can be deleted"))
     */
    public function destroy(Order $order): JsonResponse
    {
        if ($this->isCustomer()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        // Orders are sales history; cancel first so stock is returned.
        if ($order->status !== OrderStatus::Cancelled) {
            return $this->jsonResponse(['message' => 'لا يمكن حذف الطلب إلا بعد إلغائه'], 422);
        }

        $order->delete();

        return $this->jsonResponse(null, 204);
    }

    /**
     * @OA\Post(path="/orders/{id}/assign-delegate", tags={"Orders"}, summary="Assign a delegate",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="delegate_id", type="integer"))),
     *
     *     @OA\Response(response=200, description="Delegate assigned"),
     *     @OA\Response(response=422, description="Delegate missing or inactive"))
     */
    public function assignDelegate(Request $request, Order $order): JsonResponse
    {
        if (! $this->canAccessOrder($order)) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validate([
            'delegate_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $delegate = $this->activeDelegate($data['delegate_id']);

        if (! $delegate) {
            return $this->jsonResponse(['message' => 'المندوب غير موجود أو غير نشط'], 422);
        }

        $order->update(['delegate_id' => $delegate->id]);

        return $this->jsonResponse($order->load(['user', 'address', 'deliveryZone', 'items.productVariant', 'delegate']));
    }
}
