<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\Idempotency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    // The socket handshake is authorised with the same bearer token as the API
    // (the dashboard has no session cookie), at /api/v1/broadcasting/auth.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => 'api/v1', 'middleware' => ['auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn ($request) => $request->is('api/*') ? null : '/login');
        $middleware->alias([
            'permission' => CheckPermission::class,
            'idempotent' => Idempotency::class,
        ]);

        $proxies = env('TRUSTED_PROXIES');
        if ($proxies) {
            $middleware->trustProxies($proxies === '**' ? '*' : explode(',', $proxies));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'يجب تسجيل الدخول',
                ], 401, [], JSON_UNESCAPED_UNICODE);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح',
                ], 403, [], JSON_UNESCAPED_UNICODE);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'العنصر غير موجود',
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'الرابط غير موجود',
                ], 404, [], JSON_UNESCAPED_UNICODE);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'محاولات كثيرة، حاول مرة أخرى بعد قليل',
                ], 429, $e->getHeaders(), JSON_UNESCAPED_UNICODE);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'البيانات المدخلة غير صحيحة',
                    'errors' => $e->errors(),
                ], 422, [], JSON_UNESCAPED_UNICODE);
            }
        });
    })->create();
