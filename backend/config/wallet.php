<?php

return [
    'currency' => 'LYD',

    // Allowed top-up amount per request (customer requests and gateway checkouts).
    'min_topup' => (float) env('WALLET_MIN_TOPUP', 5),
    'max_topup' => (float) env('WALLET_MAX_TOPUP', 50000),

    'gateway' => [
        // Driver bound to App\Services\Payments\PaymentGateway. "sandbox" serves a
        // local checkout page; add a driver class per real provider.
        'driver' => env('WALLET_GATEWAY', 'sandbox'),
        // HMAC key used to sign gateway callbacks.
        'secret' => env('WALLET_GATEWAY_SECRET', env('APP_KEY')),
        // Where the customer lands after checkout (relative to the site root).
        'return_url' => env('WALLET_GATEWAY_RETURN_URL', '/customer/wallet'),
    ],
];
