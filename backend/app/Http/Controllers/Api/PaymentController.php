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
