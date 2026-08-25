<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use App\Models\CafeBranch;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Admin Dashboard", description="Admin platform analytics")
 */
class DashboardController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/dashboard",
     *     tags={"Admin Dashboard"},
     *     summary="Get admin dashboard analytics",
     *     description="Returns overview statistics, recent orders, monthly revenue, low stock alerts, and activity logs.",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard data",
     *         @OA\JsonContent(
     *             @OA\Property(property="stats", type="object",
     *                 @OA\Property(property="orders", type="integer", example=188),
     *                 @OA\Property(property="products", type="integer", example=12),
     *                 @OA\Property(property="branches", type="integer", example=10),
     *                 @OA\Property(property="revenue", type="number", format="float", example=9903.31),
     *                 @OA\Property(property="lowStock", type="integer", example=3)
     *             ),
     *             @OA\Property(property="ordersByStatus", type="object", additionalProperties={"type": "integer"}),
     *             @OA\Property(property="recentOrders", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="topProducts", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="monthlyRevenue", type="array", @OA\Items(
     *                 @OA\Property(property="month", type="string", example="2026-08"),
     *                 @OA\Property(property="revenue", type="number", format="float", example=1071.75)
     *             )),
     *             @OA\Property(property="recentLogs", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="lowStockItems", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $orders = Order::all();
        $products = Product::count();
        $branches = CafeBranch::count();
        $inventory = Inventory::with('productVariant.product')->get();

        $revenue = $orders->sum(fn ($o) => (float) $o->total_amount);
        $lowStock = $inventory->filter(fn ($i) => (float) $i->quantity < 10);

        $ordersByStatus = $orders->groupBy('status')
            ->map(fn ($group) => $group->count())
            ->sortDesc();

        $recentOrders = Order::with('user')
            ->orderByDesc('order_date')
            ->limit(5)
            ->get();

        $topProducts = OrderItem::select('product_variant_id', DB::raw('SUM(quantity) as total_qty'))
            ->with('productVariant.product')
            ->groupBy('product_variant_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $variant = $item->productVariant;
                $name = $variant?->product?->name ?? 'منتج';
                $label = $variant?->attribute_value ? "{$name} - {$variant->attribute_value}" : $name;

                return [
                    'id' => $variant?->id,
                    'product_id' => $variant?->product?->id,
                    'name' => $label,
                    'quantity' => (int) $item->total_qty,
                ];
            });

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
                $monthlyRevenue[$month]['revenue'] = round($monthlyRevenue[$month]['revenue'] + (float) $order->total_amount, 2);
            }
        }

        $recentLogs = ActivityLog::latest('created_at')->limit(5)->get();

        return $this->jsonResponse([
            'stats' => [
                'orders' => $orders->count(),
                'products' => $products,
                'branches' => $branches,
                'revenue' => round($revenue, 2),
                'lowStock' => $lowStock->count(),
            ],
            'ordersByStatus' => $ordersByStatus,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'monthlyRevenue' => array_values($monthlyRevenue),
            'recentLogs' => $recentLogs,
            'lowStockItems' => $lowStock->take(5)->values(),
        ]);
    }
}
