<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use App\Models\Address;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\BusinessTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseApiController
{
    private const LOW_STOCK_THRESHOLD = 10;

    public function monthlyStats(string $year, string $month): JsonResponse
    {
        $year = (int) $year;
        $month = (int) $month;
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            return $this->jsonResponse(['message' => 'شهر غير صالح'], 422);
        }

        // The month as the office lived it, as UTC bounds for the queries.
        $local = Carbon::create($year, $month, 1, 0, 0, 0, BusinessTime::tz());
        $start = $local->copy()->startOfMonth()->utc();
        $end = $local->copy()->endOfMonth()->utc();

        $orders = Order::whereBetween('placed_at', [$start, $end])->get();
        $revenue = round($orders->sum(fn ($o) => (float) $o->total_amount), 2);

        $ordersByStatus = $orders->groupBy(fn (Order $o) => $o->status->value)
            ->map(fn ($group) => $group->count())
            ->sortDesc();

        $topProducts = $this->topProducts(
            OrderItem::whereHas('order', fn ($q) => $q->whereBetween('placed_at', [$start, $end])),
            10
        );

        $recentOrders = Order::with('user')
            ->whereBetween('placed_at', [$start, $end])
            ->orderByDesc('placed_at')
            ->limit(10)
            ->get();

        return $this->jsonResponse([
            'month' => $local->format('Y-m'),
            'stats' => [
                'orders' => $orders->count(),
                'revenue' => $revenue,
                'avgOrder' => $orders->count() > 0 ? round($revenue / $orders->count(), 2) : 0.0,
            ],
            'ordersByStatus' => $ordersByStatus,
            'topProducts' => $topProducts,
            'recentOrders' => $recentOrders,
        ]);
    }

    public function index(): JsonResponse
    {
        $orders = Order::all();
        $lowStock = Inventory::with('productVariant.product', 'warehouse:id,name')
            ->where('quantity', '<', self::LOW_STOCK_THRESHOLD)
            ->orderBy('quantity')
            ->get();

        $revenue = $orders->sum(fn ($o) => (float) $o->total_amount);

        $ordersByStatus = $orders->groupBy(fn (Order $o) => $o->status->value)
            ->map(fn ($group) => $group->count())
            ->sortDesc();

        $recentOrders = Order::with('user')
            ->orderByDesc('placed_at')
            ->limit(5)
            ->get();

        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = BusinessTime::now()->startOfMonth()->subMonths($i);
            $monthlyRevenue[$month->format('Y-m')] = [
                'month' => $month->format('Y-m'),
                'revenue' => 0.0,
            ];
        }

        foreach ($orders as $order) {
            $month = BusinessTime::format($order->placed_at, 'Y-m');
            if (isset($monthlyRevenue[$month])) {
                $monthlyRevenue[$month]['revenue'] = round($monthlyRevenue[$month]['revenue'] + (float) $order->total_amount, 2);
            }
        }

        return $this->jsonResponse([
            'stats' => [
                'orders' => $orders->count(),
                'products' => Product::count(),
                'branches' => Address::count(),
                'revenue' => round($revenue, 2),
                'lowStock' => $lowStock->count(),
            ],
            'ordersByStatus' => $ordersByStatus,
            'recentOrders' => $recentOrders,
            'topProducts' => $this->topProducts(OrderItem::query(), 5),
            'monthlyRevenue' => array_values($monthlyRevenue),
            'recentLogs' => ActivityLog::latest('created_at')->limit(5)->get(),
            'lowStockItems' => $lowStock->take(5)->values(),
        ]);
    }

    // Best sellers grouped by variant, labelled from the order-time snapshot.
    private function topProducts($itemsQuery, int $limit)
    {
        return $itemsQuery
            ->select(
                'product_variant_id',
                DB::raw('MAX(product_name) as product_name'),
                DB::raw('MAX(variant_name) as variant_name'),
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(quantity * unit_price) as total_revenue')
            )
            ->with('productVariant:id,product_id')
            ->groupBy('product_variant_id')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->product_variant_id,
                'product_id' => $item->productVariant?->product_id,
                'name' => $item->variant_name ? "{$item->product_name} - {$item->variant_name}" : $item->product_name,
                'quantity' => (int) $item->total_qty,
                'revenue' => round((float) $item->total_revenue, 2),
            ]);
    }
}
