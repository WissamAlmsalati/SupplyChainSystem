<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PaymentRequest;
use App\Models\Payment;
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
    public function store(PaymentRequest $request): JsonResponse
    {
        $payment = Payment::create($request->validated());

        return $this->jsonResponse($payment->load('order'), 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        return $this->jsonResponse($payment->load('order'));
    }

    public function update(PaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment->update($request->validated());

        return $this->jsonResponse($payment->load('order'));
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $payment->delete();

        return $this->jsonResponse(null, 204);
    }
}
