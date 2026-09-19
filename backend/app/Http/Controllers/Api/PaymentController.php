<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Requests\Api\PaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Storefront", description="Public storefront endpoints")
 */
class PaymentController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->paginated(Payment::with('order')->orderByDesc('id')->paginate(15));
    }

    /**
     * @OA\Post(path="/payments", tags={"Payments"}, summary="Record a payment against an order", security={{"bearerAuth":{}}},
     *     description="A payment can never exceed what the order still owes, and a cancelled order takes none at all. The same rule applies wherever a payment is created — the dashboard, a delegate marking an order delivered, or a wallet checkout.",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"order_id","amount","method","status"},
     *
     *         @OA\Property(property="order_id", type="integer", example=58),
     *         @OA\Property(property="amount", type="number", format="float", example=40),
     *         @OA\Property(property="method", type="string", enum={"cash","card","bank_transfer","wallet"}),
     *         @OA\Property(property="status", type="string", enum={"pending","paid","failed","refunded"}),
     *         @OA\Property(property="paid_at", type="string", format="date-time", nullable=true))),
     *
     *     @OA\Response(response=201, description="Recorded.",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Created",
     *             example={"success": true, "message": "تم الإنشاء بنجاح", "data": {"id": 91, "order_id": 58, "amount": "40.00", "method": "cash", "status": "paid"}})),
     *
     *     @OA\Response(response=422, description="More than the order still owes, zero or less, or the order is cancelled.",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError",
     *             example={"success": false, "message": "البيانات المدخلة غير صحيحة", "errors": {"amount": {"المبلغ يتجاوز المتبقي على الطلب (40.00 د.ل)"}}})),
     *
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"))
     */
    public function store(PaymentRequest $request, WalletService $wallets): JsonResponse
    {
        $data = $request->validated();

        // A wallet payment has to take the money out of the wallet.
        if ($data['method'] === PaymentMethod::Wallet->value) {
            $payment = $wallets->payFromWallet(Order::with('user')->findOrFail($data['order_id']), (float) $data['amount']);
        } else {
            $payment = Payment::create($data);
        }

        return $this->jsonResponse($payment->load('order'), 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        return $this->jsonResponse($payment->load('order'));
    }

    public function update(PaymentRequest $request, Payment $payment): JsonResponse
    {
        if ($refused = $this->guardLedgerBacked($payment)) {
            return $refused;
        }

        $data = $request->validated();
        if ((int) $data['order_id'] !== $payment->order_id) {
            return $this->jsonResponse(['message' => 'لا يمكن نقل دفعة إلى طلب آخر'], 422);
        }
        if ($data['method'] === PaymentMethod::Wallet->value) {
            return $this->jsonResponse(['message' => 'سجّل دفعة المحفظة كدفعة جديدة حتى يُخصم المبلغ من المحفظة'], 422);
        }

        $payment->update($data);

        return $this->jsonResponse($payment->load('order'));
    }

    public function destroy(Payment $payment): JsonResponse
    {
        if ($refused = $this->guardLedgerBacked($payment)) {
            return $refused;
        }

        $payment->delete();

        return $this->jsonResponse(null, 204);
    }

    /**
     * Some payment rows are one half of a pair: a wallet payment has a wallet
     * debit behind it, a delegate's collection has a custody entry, a refunded
     * payment has a wallet credit. Editing or deleting the row alone would
     * leave the other ledger saying something else, so those rows are fixed;
     * a mistake in them is corrected with a wallet or custody adjustment,
     * which leaves its own trace.
     */
    private function guardLedgerBacked(Payment $payment): ?JsonResponse
    {
        $reason = match (true) {
            $payment->method === PaymentMethod::Wallet => 'دفعة من المحفظة',
            $payment->collected_by !== null => 'دفعة حصّلها مندوب ومسجلة في عهدته',
            $payment->status === PaymentStatus::Refunded => 'دفعة أُعيدت إلى محفظة الزبون',
            default => null,
        };

        return $reason === null ? null : $this->jsonResponse([
            'message' => "لا يمكن تعديل أو حذف {$reason}. صحّح الخطأ بتعديل إداري على المحفظة أو العهدة.",
        ], 422);
    }
}
