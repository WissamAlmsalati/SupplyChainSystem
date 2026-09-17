<?php

namespace App\Http\Controllers\Api;

use App\Enums\TopupMethod;
use App\Enums\TopupStatus;
use App\Enums\WalletTransactionType;
use App\Models\WalletTopup;
use App\Services\Payments\PaymentGateway;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CafeWalletController extends BaseApiController
{
    public function __construct(private WalletService $wallets) {}

    /**
     * @OA\Get(path="/cafe/wallet", tags={"Cafe Wallet"}, summary="Wallet balance with latest transactions", security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="balance, currency, recent_transactions, pending_topups"))
     */
    public function show(): JsonResponse
    {
        $wallet = $this->wallets->walletFor(auth()->user());

        return $this->jsonResponse(['data' => [
            'id' => $wallet->id,
            'balance' => $wallet->balance,
            'currency' => config('wallet.currency'),
            'is_active' => $wallet->is_active,
            'min_topup' => config('wallet.min_topup'),
            'max_topup' => config('wallet.max_topup'),
            'pending_topups' => $wallet->topups()->where('status', TopupStatus::Pending->value)->count(),
            'recent_transactions' => $wallet->transactions()->limit(10)->get(),
        ]]);
    }

    /**
     * @OA\Get(path="/cafe/wallet/transactions", tags={"Cafe Wallet"}, summary="Wallet transactions", security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"topup","payment","refund","adjustment"})),
     *     @OA\Response(response=200, description="Paginated transactions"))
     */
    public function transactions(Request $request): JsonResponse
    {
        $query = $this->wallets->walletFor(auth()->user())->transactions();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * @OA\Get(path="/cafe/wallet/topups", tags={"Cafe Wallet"}, summary="My top-up requests", security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","approved","rejected","cancelled","failed"})),
     *     @OA\Response(response=200, description="Paginated top-ups"))
     */
    public function topups(Request $request): JsonResponse
    {
        $query = WalletTopup::where('user_id', auth()->id())->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * @OA\Post(path="/cafe/wallet/topups", tags={"Cafe Wallet"}, summary="Request a bank-transfer top-up with its receipt; an admin approves it", security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *         required={"amount","method","reference_number","receipt"},
     *         @OA\Property(property="amount", type="number", example=100),
     *         @OA\Property(property="method", type="string", enum={"bank_transfer"}),
     *         @OA\Property(property="reference_number", type="string", description="Transfer reference"),
     *         @OA\Property(property="receipt", type="string", format="binary", description="Transfer receipt: jpg/png/webp/heic or pdf, max 5MB"),
     *         @OA\Property(property="note", type="string")))),
     *     @OA\Response(response=201, description="Pending top-up created"), @OA\Response(response=422, description="Validation error"))
     */
    public function storeTopup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:' . config('wallet.min_topup'), 'max:' . config('wallet.max_topup')],
            'method' => ['required', Rule::in([TopupMethod::BankTransfer->value])],
            'reference_number' => ['required', 'string', 'max:100'],
            // The transfer receipt (photo or PDF) is reviewed by an admin.
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:5120'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $topup = $this->wallets->requestTopup(auth()->user(), (float) $data['amount'], TopupMethod::from($data['method']), [
            'reference_number' => $data['reference_number'] ?? null,
            'receipt_path' => $request->file('receipt')?->store('wallet-receipts', 'public'),
            'note' => $data['note'] ?? null,
        ]);

        return $this->jsonResponse(['data' => $topup, 'message' => 'تم إرسال طلب الشحن، سيتم مراجعته من الإدارة'], 201);
    }

    /**
     * @OA\Post(path="/cafe/wallet/topups/{id}/cancel", tags={"Cafe Wallet"}, summary="Cancel my pending top-up request", security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Cancelled"), @OA\Response(response=422, description="Already processed"))
     */
    public function cancelTopup(int $id): JsonResponse
    {
        $topup = WalletTopup::where('user_id', auth()->id())->findOrFail($id);

        return $this->jsonResponse($this->wallets->cancelTopup($topup));
    }

    /**
     * @OA\Post(path="/cafe/wallet/topups/gateway", tags={"Cafe Wallet"}, summary="Start an online top-up; open checkout_url to pay", security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"amount"}, @OA\Property(property="amount", type="number", example=150))),
     *     @OA\Response(response=201, description="topup + checkout_url; the wallet is credited when the gateway confirms"))
     */
    public function gatewayTopup(Request $request, PaymentGateway $gateway): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:' . config('wallet.min_topup'), 'max:' . config('wallet.max_topup')],
        ]);

        $topup = $this->wallets->requestTopup(auth()->user(), (float) $data['amount'], TopupMethod::Gateway);
        $checkoutUrl = $gateway->createCheckout($topup);

        return $this->jsonResponse([
            'data' => [
                'topup' => $topup->fresh(),
                'checkout_url' => $checkoutUrl,
                'gateway' => $gateway->name(),
            ],
        ], 201);
    }
}
