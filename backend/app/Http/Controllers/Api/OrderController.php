<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\OrderRequest;
use App\Models\AppUser;
use App\Models\CafeBranch;
use App\Models\Notification;
use App\Models\Order;
use App\Services\DelegateAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Admin Orders", description="Admin platform order management")
 */
class OrderController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/orders",
     *     tags={"Admin Orders"},
     *     summary="List orders",
     *     @OA\Response(response=200, description="Paginated list of orders")
     * )
     */
    protected function isCafeAdmin(): bool
    {
        return auth()->user()?->userType?->name === 'cafe';
    }

    protected function cafeAdminCafeId(): ?int
    {
        return auth()->user()?->cafe_id;
    }

    protected function orderQuery()
    {
        $query = Order::with(['user', 'branch', 'deliveryZone', 'delegate']);

        if ($this->isCafeAdmin()) {
            $query->whereHas('branch', fn ($q) => $q->where('cafe_id', $this->cafeAdminCafeId()));
        }

        return $query;
    }

    protected function canAccessOrder(Order $order): bool
    {
        if (! $this->isCafeAdmin()) {
            return true;
        }

        return $order->branch?->cafe_id === $this->cafeAdminCafeId();
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->orderQuery()->orderByDesc('order_date');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('status', 'like', "%{$search}%")
                  ->orWhere('id', $search)
                  ->orWhere('order_number', 'like', "%{$search}%")
                  ->orWhereHas('branch', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('user', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
            });
        }

        return $this->jsonResponse($query->paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/orders",
     *     tags={"Admin Orders"},
     *     summary="Create an order",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/OrderRequest")),
     *     @OA\Response(response=201, description="Order created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(OrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        if ($this->isCafeAdmin()) {
            $branch = CafeBranch::find($data['branch_id'] ?? null);
            if (! $branch || $branch->cafe_id !== $this->cafeAdminCafeId()) {
                return $this->jsonResponse(['message' => 'غير مصرح'], 403);
            }
        }

        $order = DB::transaction(function () use ($data, $items) {
            $data['order_number'] = Order::generateOrderNumber();
            $order = Order::create($data);
            if ($items) {
                $order->items()->createMany($items);
            }

            return $order;
        });

        app(DelegateAssignmentService::class)->assignNearest($order);

        Notification::notifyAdmins(
            'طلب جديد',
            "تم إنشاء طلب جديد برقم {$order->order_number}",
            "/orders/{$order->id}",
            'order'
        );

        return $this->jsonResponse($order->load(['user', 'branch', 'deliveryZone', 'items.productVariant']), 201);
    }

    /**
     * @OA\Get(
     *     path="/orders/{id}",
     *     tags={"Admin Orders"},
     *     summary="Get an order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Order details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Order $order): JsonResponse
    {
        if (! $this->canAccessOrder($order)) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        return $this->jsonResponse($order->load(['user', 'branch', 'deliveryZone', 'delegate', 'items.productVariant', 'payments', 'statusLogs']));
    }

    /**
     * @OA\Put(
     *     path="/orders/{id}",
     *     tags={"Admin Orders"},
     *     summary="Update an order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/OrderRequest")),
     *     @OA\Response(response=200, description="Order updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(OrderRequest $request, Order $order): JsonResponse
    {
        if (! $this->canAccessOrder($order)) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validated();
        unset($data['items']);

        if ($this->isCafeAdmin() && isset($data['branch_id'])) {
            $branch = CafeBranch::find($data['branch_id']);
            if (! $branch || $branch->cafe_id !== $this->cafeAdminCafeId()) {
                return $this->jsonResponse(['message' => 'غير مصرح'], 403);
            }
        }

        $order->update($data);
        return $this->jsonResponse($order->load(['user', 'branch', 'deliveryZone', 'items.productVariant']));
    }

    /**
     * @OA\Delete(
     *     path="/orders/{id}",
     *     tags={"Admin Orders"},
     *     summary="Delete an order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Order deleted")
     * )
     */
    public function destroy(Order $order): JsonResponse
    {
        if (! $this->canAccessOrder($order)) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $order->delete();
        return $this->jsonResponse(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/orders/{id}/assign-delegate",
     *     tags={"Admin Orders"},
     *     summary="Assign a delegate to an order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="delegate_id", type="integer", description="ID of the active delegate")
     *     )),
     *     @OA\Response(response=200, description="Delegate assigned"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function assignDelegate(Request $request, Order $order): JsonResponse
    {
        if (! $this->canAccessOrder($order)) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validate([
            'delegate_id' => ['required', 'integer', 'exists:app_user,id'],
        ]);

        $delegateTypeId = \App\Models\UserType::where('name', 'delegate')->value('id');

        $delegate = AppUser::where('id', $data['delegate_id'])
            ->where('user_type_id', $delegateTypeId)
            ->where('is_active', true)
            ->first();

        if (! $delegate) {
            return $this->jsonResponse(['message' => 'المندوب غير موجود أو غير نشط'], 422);
        }

        $order->update(['delegate_id' => $delegate->id]);

        return $this->jsonResponse($order->load(['user', 'branch', 'deliveryZone', 'items.productVariant', 'delegate']));
    }
}
