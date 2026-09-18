<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a write safe to send twice.
 *
 * A phone on a weak network retries, and a person double-taps "confirm". Both
 * used to create two orders or two payments. A client that sends an
 * `Idempotency-Key` header now gets this promise: the first request with that
 * key does the work, and any repeat gets the first answer back instead of
 * doing the work again.
 *
 * - The key is scoped to the signed-in user and the route, so one client's key
 *   can never collide with, or read the answer of, another's.
 * - The same key with a different body is a client bug, not a retry, and is
 *   refused rather than silently answered with the wrong thing.
 * - A repeat that arrives while the first is still running gets a 409; the
 *   client should simply ask again.
 * - Only successful answers are remembered. A failure changed nothing, so
 *   trying again with the same key is exactly what the client should do.
 *
 * The header is optional, so clients already deployed keep working unchanged.
 */
class Idempotency
{
    public const HEADER = 'Idempotency-Key';

    public const REPLAY_HEADER = 'Idempotent-Replay';

    private const TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if ($key === null || $key === '' || ! $request->isMethod('POST')) {
            return $next($request);
        }

        if (strlen($key) > 100) {
            return $this->error('مفتاح الإتمام أطول من المسموح (100 حرف)', 422);
        }

        $slot = 'idem:'.sha1(($request->user()?->getAuthIdentifier() ?? $request->ip()).'|'.$request->path().'|'.$key);
        $fingerprint = sha1(json_encode($request->except(['_method'])).'|'.implode(',', array_keys($request->allFiles())));

        $lock = Cache::lock($slot.':lock', 30);
        if (! $lock->get()) {
            return $this->error('الطلب نفسه قيد التنفيذ، أعد المحاولة بعد لحظات', 409);
        }

        try {
            if ($stored = Cache::get($slot)) {
                if ($stored['fingerprint'] !== $fingerprint) {
                    return $this->error('مفتاح الإتمام استُخدم من قبل مع بيانات مختلفة', 422);
                }

                return response($stored['body'], $stored['status'], [
                    'Content-Type' => $stored['content_type'],
                    self::REPLAY_HEADER => 'true',
                ]);
            }

            $response = $next($request);

            if ($response->isSuccessful()) {
                Cache::put($slot, [
                    'fingerprint' => $fingerprint,
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(),
                    'content_type' => $response->headers->get('Content-Type', 'application/json'),
                ], now()->addHours(self::TTL_HOURS));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status, [], JSON_UNESCAPED_UNICODE);
    }
}
