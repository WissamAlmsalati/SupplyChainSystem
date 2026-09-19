<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permission codes are derived from route names: "resource.action" becomes
 * RESOURCE_VERB (orders.store → ORDERS_CREATE). The check is fail-closed: a
 * code that nobody seeded refuses the request instead of letting it through,
 * and PermissionCoverageTest keeps the route list and PermissionSeeder in sync.
 */
class CheckPermission
{
    /** Route action → permission verb. */
    public const ACTION_MAP = [
        'index' => 'VIEW',
        'show' => 'VIEW',
        'store' => 'CREATE',
        'update' => 'EDIT',
        'destroy' => 'DELETE',
        'transactions' => 'VIEW',
        'adjust' => 'EDIT',
        'approve' => 'EDIT',
        'reject' => 'EDIT',
        'receive' => 'EDIT',
        'cancel' => 'EDIT',
        'toggle-active' => 'EDIT',
        'entries' => 'VIEW',
        'summary' => 'VIEW',
        'reorder' => 'EDIT',
        'preview' => 'VIEW',
        'settle' => 'EDIT',
        'location' => 'EDIT',
        'expand-hex' => 'EDIT',
        'invoice' => 'VIEW',
    ];

    /** Routes whose code does not follow RESOURCE_VERB. */
    public const ROUTE_MAP = [
        'dashboard' => 'DASHBOARD_VIEW',
        'dashboard.monthly' => 'DASHBOARD_VIEW',
        'orders.assign-delegate' => 'ORDER_ASSIGN',
        'premium-features.update' => 'PREMIUM_FEATURES_EDIT',
        // Sending is the one notifications action that is not about your own rows.
        'notifications.send' => 'NOTIFICATIONS_SEND',
        'notifications.sent' => 'NOTIFICATIONS_SEND',
    ];

    /**
     * Route groups scoped to the signed-in user by their own controllers
     * (customer app, delegate app, gateway callbacks): no module permission.
     */
    public const EXEMPT_PREFIXES = ['customer.', 'delegate.', 'wallet.gateway.'];

    /** Resources every user owns rows of; the controller filters by user_id. */
    public const OWN_RESOURCES = ['notifications'];

    // ponytail: addresses keeps the historic CUSTOMER_BRANCHES_* permission
    // codes so existing roles keep working; only the label changed.
    public const MODULE_MAP = ['addresses' => 'CUSTOMER_BRANCHES'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'يجب تسجيل الدخول'], 401);
        }

        // A switched-off account is locked out now, not when its token happens
        // to expire: a suspended delegate must not keep recording collections.
        if (! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json(['success' => false, 'message' => 'الحساب غير نشط'], 403, [], JSON_UNESCAPED_UNICODE);
        }

        if ($user->userType?->name === 'super_admin') {
            return $next($request);
        }

        // Unnamed routes (login, logout, me, register) do their own checks.
        $routeName = $request->route()?->getName();
        $code = $routeName ? self::codeForRoute($routeName) : null;

        if ($code === null) {
            return $next($request);
        }

        // ponytail: the app roles hold codes like ORDERS_VIEW for historic
        // reasons, and the dashboard's routes do not scope by owner the way
        // customer.* and delegate.* do. With only the code check, a cafe could
        // download any cafe's invoice and a delegate could read and reassign
        // every order. Their apps live entirely under their own prefixes, so a
        // dashboard route is never theirs to call, whatever codes they hold.
        if ($user->hasRole(UserRole::Customer, UserRole::Delegate)) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403, [], JSON_UNESCAPED_UNICODE);
        }

        $user->loadMissing('userType.permissions');
        $codes = $user->userType?->permissions?->pluck('code') ?? collect();

        if (! $codes->contains($code)) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        return $next($request);
    }

    /**
     * The permission code a route name requires, or null when the route is
     * exempt from module permissions.
     */
    public static function codeForRoute(string $routeName): ?string
    {
        if (isset(self::ROUTE_MAP[$routeName])) {
            return self::ROUTE_MAP[$routeName];
        }

        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return null;
            }
        }

        if (str_starts_with($routeName, 'activity-logs.')) {
            return 'ACTIVITY_LOGS_VIEW';
        }

        // Every report, whatever it is about, is one read-only permission.
        if (str_starts_with($routeName, 'reports.')) {
            return 'REPORTS_VIEW';
        }

        $parts = explode('.', $routeName);

        if (count($parts) !== 2) {
            return null;
        }

        [$resource, $action] = $parts;

        if (in_array($resource, self::OWN_RESOURCES, true)) {
            return null;
        }

        $module = self::MODULE_MAP[$resource] ?? strtoupper(str_replace('-', '_', $resource));
        // ponytail: an action nobody mapped yields a code nobody seeded, so the
        // request is refused rather than slipping through unchecked.
        $verb = self::ACTION_MAP[$action] ?? strtoupper(str_replace('-', '_', $action));

        return "{$module}_{$verb}";
    }
}
