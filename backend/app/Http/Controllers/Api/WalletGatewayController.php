<?php

namespace App\Http\Controllers\Api;

use App\Enums\TopupMethod;
use App\Models\WalletTopup;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SandboxGateway;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Public endpoints hit by the payment provider (callback) and, for the sandbox
// driver, the checkout page the customer opens.
class WalletGatewayController extends BaseApiController
{
    /**
     * @OA\Post(path="/wallet/gateway/callback", tags={"Customer Wallet"}, summary="Payment gateway callback (signed); credits the wallet on success",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="token", type="string"), @OA\Property(property="status", type="string", enum={"paid","failed"}),
     *         @OA\Property(property="reference", type="string"), @OA\Property(property="signature", type="string"))),
     *
     *     @OA\Response(response=200, description="Processed"), @OA\Response(response=403, description="Invalid signature"))
     */
    public function callback(Request $request, PaymentGateway $gateway, WalletService $wallets)
    {
        $result = $gateway->parseCallback($request);
        if (! $result) {
            return $this->jsonResponse(['message' => 'توقيع غير صالح'], 403);
        }

        $topup = WalletTopup::where('gateway_token', $result['token'])
            ->where('method', TopupMethod::Gateway->value)
            ->firstOrFail();

        try {
            $topup = $result['paid']
                ? $wallets->approveTopup($topup, null, $result['reference'])
                : $wallets->failTopup($topup, $result['reference']);
        } catch (ValidationException) {
            // Already processed: providers retry callbacks, so this is not an error.
        }

        if ($request->boolean('redirect')) {
            // Keep the configured URL as-is (relative stays on the customer's current host;
            // redirect() would prefix APP_URL).
            return new RedirectResponse(config('wallet.gateway.return_url').'?topup='.$topup->id.'&status='.$topup->fresh()->status->value);
        }

        return $this->jsonResponse(['id' => $topup->id, 'status' => $topup->fresh()->status]);
    }

    // Sandbox checkout page (only when the sandbox driver is active).
    public function sandbox(string $token, PaymentGateway $gateway)
    {
        abort_unless($gateway instanceof SandboxGateway, 404);

        $topup = WalletTopup::with('user:id,name')->where('gateway_token', $token)->firstOrFail();
        $reference = 'SBX-'.strtoupper(Str::random(10));

        return response()->view('wallet.sandbox', [
            'topup' => $topup,
            'reference' => $reference,
            'paidSignature' => SandboxGateway::sign($token, 'paid', $reference),
            'failedSignature' => SandboxGateway::sign($token, 'failed', $reference),
        ]);
    }
}
