<?php

namespace App\Providers;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SandboxGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\Telescope;

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

        // Telescope ships in require-dev, so it simply is not there in a
        // production image. Guarding on the package rather than on the
        // environment means the class is only ever touched when it exists;
        // whether it then records anything is config/telescope.php's business.
        if (class_exists(Telescope::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
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
        // A signed-in account gets more room than an anonymous address: one
        // dashboard page is a dozen requests (the page, the sidebar badges, the
        // search box), and an admin moving quickly between pages reached 120.
        RateLimiter::for('api', fn (Request $request) => $request->user()
            ? Limit::perMinute(300)->by('user:'.$request->user()->id)
            : Limit::perMinute(120)->by('ip:'.$request->ip()));

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
