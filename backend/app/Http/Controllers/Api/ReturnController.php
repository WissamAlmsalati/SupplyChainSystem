<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreReturnRequest;
use App\Models\AppUser;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\ReturnService;
use App\Support\ArabicText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Returns", description="Goods returned after delivery: back to stock or written off, with the refund that follows")
 */
class ReturnController extends BaseApiController
{
    /**
     * @OA\Get(path="/returns", tags={"Returns"}, summary="List returns",
     *
     *     @OA\Parameter(name="search", in="query", description="Order number, customer name or phone", @OA\Schema(type="string")),
     *     @OA\Parameter(name="order_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Returns, newest first. `meta.summary` totals the filtered set."))
     */
    public function index(Request $request): JsonResponse
    {
        $query = OrderReturn::query()
            ->when($request->filled('order_id'), fn ($q) => $q->where('order_id', $request->integer('order_id')))
            ->when($request->boolean('refund_pending'), fn ($q) => $q->where('refund_amount', '>', 0)->whereNull('refund_paid_at'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->whereHas('order', fn ($o) => $o->where(fn ($inner) => ArabicText::filter($inner, $search, ['order_number'])
                    ->orWhereHas('user', fn ($u) => ArabicText::filter($u, $search, ['name', 'mobile_number']))));
            });

        $summary = [
            'total_value' => round((float) (clone $query)->sum('total_value'), 2),
            'refunded' => round((float) (clone $query)->sum('refund_amount'), 2),
            // Cash refunds recorded but not yet handed over: money the company still owes.
            'refund_pending' => round((float) (clone $query)->where('refund_amount', '>', 0)->whereNull('refund_paid_at')->sum('refund_amount'), 2),
        ];

        return $this->paginated(
            $query->with(['order:id,order_number,user_id,delegate_id', 'order.user:id,name,mobile_number', 'createdBy:id,name', 'refundPaidBy:id,name', 'refundPaidFromDelegate:id,name'])
                ->withSum('items as items_quantity', 'quantity')
                ->orderByDesc('id')
                ->paginate($request->integer('per_page', 15)),
            meta: ['summary' => $summary],
        );
    }

    /**
     * @OA\Get(path="/returns/{id}", tags={"Returns"}, summary="One return with its lines",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Return"))
     */
    public function show(OrderReturn $orderReturn): JsonResponse
    {
        return $this->jsonResponse($orderReturn->load([
            'order:id,order_number,user_id,total_amount', 'order.user:id,name,mobile_number',
            'items.orderItem', 'items.warehouse:id,name', 'createdBy:id,name',
        ]));
    }

    /**
     * @OA\Post(path="/returns", tags={"Returns"}, summary="Record a return on a delivered order",
     *     description="Restocked lines go back to the warehouse the sale left from; damaged lines are written off. The customer is refunded only what they paid beyond what the order now owes, so an unpaid order simply owes less. An order that has a return can no longer be cancelled. Send an `Idempotency-Key` header so a retry cannot record the return twice.",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"order_id","reason","items"},
     *
     *         @OA\Property(property="order_id", type="integer", example=58),
     *         @OA\Property(property="reason", type="string", example="كراتين وصلت مبللة"),
     *         @OA\Property(property="refund_method", type="string", enum={"wallet","cash","none"}, example="wallet"),
     *         @OA\Property(property="items", type="array", @OA\Items(
     *             required={"order_item_id","quantity","condition"},
     *             @OA\Property(property="order_item_id", type="integer", example=131),
     *             @OA\Property(property="quantity", type="integer", example=2),
     *             @OA\Property(property="condition", type="string", enum={"restock","damaged"}, example="damaged")
     *         ))
     *     )),
     *
     *     @OA\Response(response=201, description="Recorded"),
     *     @OA\Response(response=422, description="Not delivered yet, more than was delivered, or a paid order with no refund method",
     *
     *         @OA\JsonContent(example={"success": false, "message": "الكمية المرتجعة من «أكواب ورقية» أكبر من المتبقي (3)", "errors": {"items": {"الكمية المرتجعة من «أكواب ورقية» أكبر من المتبقي (3)"}}}))
     * )
     */
    public function store(StoreReturnRequest $request, ReturnService $returns): JsonResponse
    {
        $data = $request->validated();

        $return = $returns->create(
            Order::findOrFail($data['order_id']),
            $data['items'],
            $data['reason'],
            $data['refund_method'] ?? 'wallet',
        );

        return $this->jsonResponse(['data' => $return, 'message' => 'تم تسجيل المرتجع'], 201);
    }

    /**
     * @OA\Post(path="/returns/{id}/pay-refund", tags={"Returns"}, summary="Record that a cash refund was handed over",
     *     description="A cash refund is owed from the moment the return is recorded and stays pending until this is called. Send `delegate_id` when a delegate paid it out of the cash they hold; their custody drops by the amount. Without it, the office paid.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="delegate_id", type="integer", nullable=true, example=10))),
     *
     *     @OA\Response(response=200, description="Marked as paid"),
     *     @OA\Response(response=422, description="Not a cash refund, already paid, or the delegate holds less than the refund",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function payRefund(Request $request, OrderReturn $orderReturn, ReturnService $returns): JsonResponse
    {
        $data = $request->validate(['delegate_id' => ['nullable', 'integer', 'exists:users,id']]);
        $delegate = isset($data['delegate_id'])
            ? AppUser::whereKey($data['delegate_id'])->whereHas('userType', fn ($q) => $q->where('name', 'delegate'))->firstOrFail()
            : null;

        $return = $returns->payRefund($orderReturn, $delegate);

        return $this->jsonResponse(['data' => $return->load(['refundPaidBy:id,name', 'refundPaidFromDelegate:id,name']), 'message' => 'تم تسجيل تسليم الاسترداد']);
    }
}
