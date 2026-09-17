<?php

namespace App\Http\Controllers\Api;

use App\Enums\TopupMethod;
use App\Enums\UserRole;
use App\Models\AppUser;
use App\Models\Order;
use App\Models\WalletTopup;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DelegateWalletController extends BaseApiController
{
    public function __construct(private WalletService $wallets) {}

    private function isDelegate(): bool
    {
        return auth()->user()?->userType?->name === UserRole::Delegate->value;
    }

    /**
     * @OA\Post(path="/delegate/wallet/collect", tags={"Delegate Mobile"}, summary="Record cash received from a customer; credited to their wallet immediately", security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"amount"},
     *         @OA\Property(property="amount", type="number", example=200),
     *         @OA\Property(property="order_id", type="integer", description="An order assigned to me (identifies the customer)"),
     *         @OA\Property(property="mobile_number", type="string", description="Customer phone, when there is no order"),
     *         @OA\Property(property="note", type="string"))),
     *     @OA\Response(response=201, description="Approved top-up"), @OA\Response(response=404, description="Customer or order not found"))
     */
    public function collect(Request $request): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:' . config('wallet.min_topup'), 'max:' . config('wallet.max_topup')],
            'order_id' => ['nullable', 'integer', 'required_without:mobile_number'],
            'mobile_number' => ['nullable', 'string', 'max:20', 'required_without:order_id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $order = null;
        if (! empty($data['order_id'])) {
            $order = Order::with('user')->where('delegate_id', auth()->id())->findOrFail($data['order_id']);
            $customer = $order->user;
        } else {
            $customer = AppUser::where('mobile_number', $data['mobile_number'])
                ->whereHas('userType', fn ($q) => $q->where('name', UserRole::Customer->value))
                ->firstOrFail();
        }

        $topup = $this->wallets->collectCash(auth()->user(), $customer, (float) $data['amount'], $order, $data['note'] ?? null);

        return $this->jsonResponse([
            'data' => $topup->load('user:id,name,mobile_number'),
            'message' => 'تم تسجيل المبلغ وإضافته لمحفظة الزبون',
        ], 201);
    }

    /**
     * @OA\Get(path="/delegate/wallet/collections", tags={"Delegate Mobile"}, summary="Cash I collected", security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Paginated collections with total"))
     */
    public function collections(Request $request): JsonResponse
    {
        if (! $this->isDelegate()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $query = WalletTopup::with('user:id,name,mobile_number')
            ->where('collected_by', auth()->id())
            ->where('method', TopupMethod::DelegateCash->value);

        $total = (clone $query)->sum('amount');

        return $this->jsonResponse([
            'total' => round((float) $total, 2),
            'collections' => $this->paginatedPayload($query->orderByDesc('id')->paginate($request->integer('per_page', 15))),
        ]);
    }
}
