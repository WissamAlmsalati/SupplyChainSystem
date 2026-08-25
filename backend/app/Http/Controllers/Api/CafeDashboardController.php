<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Cafe Mobile Dashboard", description="Cafe mobile app dashboard")
 */
class CafeDashboardController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $cafeId = $user?->cafe_id;

        if (! $cafeId) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $orders = Order::whereHas('branch', fn ($q) => $q->where('cafe_id', $cafeId))->get();
        $branchesCount = $user->cafe?->branches()->count() ?? 0;
        $revenue = $orders->sum(fn ($o) => (float) $o->total_amount);

        $ordersByStatus = $orders->groupBy('status')
            ->map(fn ($group) => $group->count())
            ->sortDesc();

        $recentOrders = Order::with('user')
            ->whereHas('branch', fn ($q) => $q->where('cafe_id', $cafeId))
            ->orderByDesc('order_date')
            ->limit(5)
            ->get();

        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthlyRevenue[$month->format('Y-m')] = [
                'month' => $month->format('Y-m'),
                'revenue' => 0.0,
            ];
        }

        foreach ($orders as $order) {
            if (! $order->order_date) {
                continue;
            }
            $month = Carbon::parse($order->order_date)->format('Y-m');
            if (isset($monthlyRevenue[$month])) {
                $monthlyRevenue[$month]['revenue'] += (float) $order->total_amount;
            }
        }

        return $this->jsonResponse([
            'cafe' => $user->cafe?->only(['id', 'name', 'contact_info']),
            'stats' => [
                'orders' => $orders->count(),
                'branches' => $branchesCount,
                'revenue' => number_format($revenue, 2),
            ],
            'ordersByStatus' => $ordersByStatus,
            'recentOrders' => $recentOrders,
            'monthlyRevenue' => array_values($monthlyRevenue),
        ]);
    }
}
