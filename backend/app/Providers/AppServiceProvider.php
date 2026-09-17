<?php

namespace App\Providers;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SandboxGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => match (config('wallet.gateway.driver')) {
            'sandbox' => new SandboxGateway,
            default => throw new \RuntimeException('Unknown wallet gateway driver: '.config('wallet.gateway.driver')),
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

        $this->configureRateLimiting();
    }

    /**
     * Counters live in the cache store (Redis in Docker). "api" is the ceiling
     * per client; "auth" and "otp" stop password and 6-digit code guessing,
     * keyed by IP and by the account being targeted.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user() ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('account:'.strtolower((string) ($request->input('email') ?: $request->input('phone_number') ?: $request->ip()))),
        ]);

        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(5)->by('ip:'.$request->ip()),
            Limit::perHour(10)->by('target:'.($request->input('mobile_number') ?: $request->input('phone_number') ?: $request->input('token') ?: $request->ip())),
        ]);
    }
}
