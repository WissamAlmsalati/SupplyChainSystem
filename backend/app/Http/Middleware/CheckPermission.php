<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    protected array $actionMap = [
        'index' => 'VIEW',
        'show' => 'VIEW',
        'store' => 'CREATE',
        'update' => 'EDIT',
        'destroy' => 'DELETE',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'يجب تسجيل الدخول'], 401);
        }

        if ($user->userType?->name === 'super_admin') {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (! $routeName || str_ends_with($routeName, '.logout')) {
            return $next($request);
        }

        if ($routeName === 'dashboard') {
            return $this->requirePermission($user, 'DASHBOARD_VIEW', $next, $request);
        }

        if (str_starts_with($routeName, 'activity-logs')) {
            return $this->requirePermission($user, 'ACTIVITY_LOGS_VIEW', $next, $request);
        }

        $parts = explode('.', $routeName);
        if (count($parts) !== 2) {
            return $next($request);
        }

        [$resource, $action] = $parts;
        $suffix = $this->actionMap[$action] ?? null;

        if (! $suffix) {
            return $next($request);
        }

        // ponytail: addresses keeps the historic CAFE_BRANCHES_* permission
        // codes so existing roles keep working; only the label changed.
        $moduleMap = ['addresses' => 'CAFE_BRANCHES'];
        $module = $moduleMap[$resource] ?? strtoupper(str_replace('-', '_', $resource));
        $code = "{$module}_{$suffix}";

        return $this->requirePermission($user, $code, $next, $request);
    }

    protected function requirePermission($user, string $code, Closure $next, Request $request): Response
    {
        if (! Permission::where('code', $code)->exists()) {
            return $next($request);
        }

        $user->loadMissing('userType.permissions');
        $codes = $user->userType?->permissions?->pluck('code') ?? collect();

        if (! $codes->contains($code)) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        return $next($request);
    }
}
