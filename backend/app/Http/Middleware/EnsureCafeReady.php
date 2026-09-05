<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cafe-type users must have created a cafe, and that cafe must be
 * approved, before they can use ordering/branch endpoints.
 * Other user types pass through untouched.
 */
class EnsureCafeReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->userType?->name !== 'cafe') {
            return $next($request);
        }

        if (! $user->cafe_id) {
            return response()->json([
                'success' => false,
                'message' => 'يجب إضافة بيانات المقهى أولاً',
                'has_cafe' => false,
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }

        $user->loadMissing('cafe');

        if (! $user->cafe?->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'المقهى بانتظار موافقة الإدارة',
                'has_cafe' => true,
                'cafe_active' => false,
            ], 403, [], JSON_UNESCAPED_UNICODE);
        }

        return $next($request);
    }
}
