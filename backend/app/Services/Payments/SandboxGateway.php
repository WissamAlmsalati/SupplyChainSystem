<?php

namespace App\Services\Payments;

use App\Models\WalletTopup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// Local stand-in for a real provider: serves its own checkout page and posts a
// signed callback, so the whole top-up flow can run without external accounts.
class SandboxGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function createCheckout(WalletTopup $topup): string
    {
        if (! $topup->gateway_token) {
            $topup->update(['gateway_token' => Str::random(40)]);
        }

        return '/api/v1/wallet/gateway/sandbox/'.$topup->gateway_token;
    }

    public function parseCallback(Request $request): ?array
    {
        $token = (string) $request->input('token');
        $status = (string) $request->input('status');
        $reference = $request->input('reference');

        if (! hash_equals(self::sign($token, $status, $reference), (string) $request->input('signature'))) {
            return null;
        }

        return ['token' => $token, 'paid' => $status === 'paid', 'reference' => $reference];
    }

    public static function sign(string $token, string $status, ?string $reference): string
    {
        return hash_hmac('sha256', "{$token}|{$status}|{$reference}", (string) config('wallet.gateway.secret'));
    }
}
