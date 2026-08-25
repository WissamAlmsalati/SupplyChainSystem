<?php

namespace App\Http\Controllers\Api;

use App\Models\CafeBranch;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Cafe Mobile Dashboard", description="Cafe mobile app dashboard")
 */
class CafeDashboardController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/cafe/dashboard",
     *     tags={"Cafe Mobile Dashboard"},
     *     summary="Get cafe dashboard analytics",
     *     @OA\Response(response=200, description="Dashboard data")
     * )
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $cafeId = $user?->cafe_id;

        if (! $cafeId) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $orderQuery = Order::whereHas('branch', fn ($q) => $q->where('cafe_id', $cafeId));
        $orders = (clone $orderQuery)->get();
        $branchesCount = $user->cafe?->branches()->count() ?? 0;
        $purchases = $orders->sum(fn ($o) => (float) $o->total_amount);

        $today = Carbon::now()->startOfDay();
        $weekStart = Carbon::now()->startOfWeek();
        $monthStart = Carbon::now()->startOfMonth();

        $periodStats = [
            'today' => $this->periodStats($orderQuery, $today, Carbon::now()->endOfDay()),
            'this_week' => $this->periodStats($orderQuery, $weekStart, Carbon::now()->endOfWeek()),
            'this_month' => $this->periodStats($orderQuery, $monthStart, Carbon::now()->endOfMonth()),
        ];

        $ordersByStatus = $orders->groupBy('status')
            ->map(fn ($group) => $group->count())
            ->sortDesc();

        $recentOrders = (clone $orderQuery)
            ->with('user')
            ->orderByDesc('order_date')
            ->limit(5)
            ->get();

        $monthlyPurchases = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthlyPurchases[$month->format('Y-m')] = [
                'month' => $month->format('Y-m'),
                'purchases' => 0.0,
            ];
        }

        foreach ($orders as $order) {
            if (! $order->order_date) {
                continue;
            }
            $month = Carbon::parse($order->order_date)->format('Y-m');
            if (isset($monthlyPurchases[$month])) {
                $monthlyPurchases[$month]['purchases'] += (float) $order->total_amount;
            }
        }

        $topProducts = OrderItem::query()
            ->select('product_variant_id', DB::raw('SUM(quantity) as total_quantity'))
            ->whereHas('order.branch', fn ($q) => $q->where('cafe_id', $cafeId))
            ->with('productVariant.product')
            ->groupBy('product_variant_id')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'product_variant_id' => $item->product_variant_id,
                'product_name' => $item->productVariant?->product?->name,
                'variant_value' => $item->productVariant?->attribute_value,
                'total_quantity' => (int) $item->total_quantity,
            ]);

        $branchesComparison = CafeBranch::where('cafe_id', $cafeId)
            ->withCount('orders')
            ->withSum('orders', 'total_amount')
            ->get()
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'orders_count' => $branch->orders_count,
                'purchases' => round((float) $branch->orders_sum_total_amount, 2),
            ]);

        return $this->jsonResponse([
            'cafe' => $user->cafe?->only(['id', 'name', 'contact_info']),
            'stats' => [
                'orders' => $orders->count(),
                'branches' => $branchesCount,
                'purchases' => round($purchases, 2),
                'pending_orders' => $orders->where('status', 'pending')->count(),
            ],
            'periodStats' => $periodStats,
            'ordersByStatus' => $ordersByStatus,
            'recentOrders' => $recentOrders,
            'monthlyPurchases' => array_values($monthlyPurchases),
            'topProducts' => $topProducts,
            'branchesComparison' => $branchesComparison,
        ]);
    }

    private function periodStats($orderQuery, Carbon $from, Carbon $to): array
    {
        $orders = (clone $orderQuery)
            ->whereBetween('order_date', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->get();

        return [
            'orders' => $orders->count(),
            'purchases' => round($orders->sum(fn ($o) => (float) $o->total_amount), 2),
        ];
    }
}
