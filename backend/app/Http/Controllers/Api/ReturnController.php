<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreReturnRequest;
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
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->whereHas('order', fn ($o) => $o->where(fn ($inner) => ArabicText::filter($inner, $search, ['order_number'])
                    ->orWhereHas('user', fn ($u) => ArabicText::filter($u, $search, ['name', 'mobile_number']))));
            });

        $summary = [
            'total_value' => round((float) (clone $query)->sum('total_value'), 2),
            'refunded' => round((float) (clone $query)->sum('refund_amount'), 2),
        ];

        return $this->paginated(
            $query->with(['order:id,order_number,user_id', 'order.user:id,name,mobile_number', 'createdBy:id,name'])
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
}
