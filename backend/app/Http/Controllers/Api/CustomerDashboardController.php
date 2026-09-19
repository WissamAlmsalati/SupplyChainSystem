<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\BusinessTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CustomerDashboardController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $user = auth()->user();

        $orderQuery = Order::where('user_id', $user->id);
        $orders = (clone $orderQuery)->get();
        $addressesCount = Address::where('user_id', $user->id)->count();
        $purchases = $orders->sum(fn ($o) => (float) $o->total_amount);

        $periodStats = [
            'today' => $this->periodStats($orderQuery, BusinessTime::startOf('day'), BusinessTime::endOf('day')),
            'this_week' => $this->periodStats($orderQuery, BusinessTime::startOf('week'), BusinessTime::endOf('week')),
            'this_month' => $this->periodStats($orderQuery, BusinessTime::startOf('month'), BusinessTime::endOf('month')),
        ];

        $ordersByStatus = $orders->groupBy(fn (Order $o) => $o->status->value)
            ->map(fn ($group) => $group->count())
            ->sortDesc();

        $recentOrders = (clone $orderQuery)
            ->with('user')
            ->orderByDesc('placed_at')
            ->limit(5)
            ->get();

        $monthlyPurchases = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = BusinessTime::now()->startOfMonth()->subMonths($i);
            $monthlyPurchases[$month->format('Y-m')] = [
                'month' => $month->format('Y-m'),
                'purchases' => 0.0,
            ];
        }

        foreach ($orders as $order) {
            $month = BusinessTime::format($order->placed_at, 'Y-m');
            if (isset($monthlyPurchases[$month])) {
                $monthlyPurchases[$month]['purchases'] += (float) $order->total_amount;
            }
        }

        $topProducts = OrderItem::query()
            ->select('product_variant_id', DB::raw('MAX(product_name) as product_name'), DB::raw('MAX(variant_name) as variant_name'), DB::raw('SUM(quantity) as total_quantity'))
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
            ->groupBy('product_variant_id')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'product_variant_id' => $item->product_variant_id,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'total_quantity' => (int) $item->total_quantity,
            ]);

        $addressesComparison = Address::where('user_id', $user->id)
            ->withCount('orders')
            ->withSum('orders', 'total_amount')
            ->get()
            ->map(fn ($address) => [
                'id' => $address->id,
                'name' => $address->name,
                'orders_count' => $address->orders_count,
                'purchases' => round((float) $address->orders_sum_total_amount, 2),
            ]);

        return $this->jsonResponse([
            'user' => $user->only(['id', 'name', 'mobile_number']),
            'stats' => [
                'orders' => $orders->count(),
                'branches' => $addressesCount,
                'purchases' => round($purchases, 2),
                'pending_orders' => $orders->where('status', OrderStatus::Pending)->count(),
            ],
            'periodStats' => $periodStats,
            'ordersByStatus' => $ordersByStatus,
            'recentOrders' => $recentOrders,
            'monthlyPurchases' => array_values($monthlyPurchases),
            'topProducts' => $topProducts,
            'branchesComparison' => $addressesComparison,
        ]);
    }

    private function periodStats($orderQuery, Carbon $from, Carbon $to): array
    {
        $orders = (clone $orderQuery)
            ->whereBetween('placed_at', [$from, $to])
            ->get();

        return [
            'orders' => $orders->count(),
            'purchases' => round($orders->sum(fn ($o) => (float) $o->total_amount), 2),
        ];
    }
}
