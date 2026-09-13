<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Services\Payments\PaymentGateway::class, fn () => match (config('wallet.gateway.driver')) {
            'sandbox' => new \App\Services\Payments\SandboxGateway(),
            default => throw new \RuntimeException('Unknown wallet gateway driver: ' . config('wallet.gateway.driver')),
        });
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ponytail: force Arabic for all API validation and framework messages
        app()->setLocale('ar');
    }
}
