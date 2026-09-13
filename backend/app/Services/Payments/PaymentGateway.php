<?php

namespace App\Services\Payments;

use App\Models\WalletTopup;
use Illuminate\Http\Request;

// A wallet top-up checkout provider. Implement one class per provider and select
// it with WALLET_GATEWAY (see config/wallet.php and AppServiceProvider).
interface PaymentGateway
{
    public function name(): string;

    // Starts a checkout for a pending gateway top-up and returns the URL the customer opens.
    public function createCheckout(WalletTopup $topup): string;

    /**
     * Validates a provider callback. Returns null when the signature is invalid.
     *
     * @return array{token:string, paid:bool, reference:?string}|null
     */
    public function parseCallback(Request $request): ?array;
}
