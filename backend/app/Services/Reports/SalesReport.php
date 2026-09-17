<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

// Sales figures for a period. Revenue counts every order that was not
// cancelled (by placed_at); cancelled orders are listed separately so the
// numbers reconcile with the dashboard's raw counts.
class SalesReport
{
    public function build(Period $period, string $groupBy = 'day'): array
    {
        $base = Order::query()->whereBetween('placed_at', [$period->from, $period->to]);
        $sold = (clone $base)->where('status', '!=', OrderStatus::Cancelled->value);

        $ordersCount = (clone $sold)->count();
        $revenue = round((float) (clone $sold)->sum('total_amount'), 2);
        $deliveryFees = round((float) (clone $sold)->sum('delivery_fee'), 2);

        $format = $groupBy === 'month' ? '%Y-%m' : '%Y-%m-%d';
        $bucket = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('{$format}', placed_at)"
            : "DATE_FORMAT(placed_at, '{$format}')";

        $series = (clone $sold)
            ->selectRaw("{$bucket} as bucket, COUNT(*) as orders, SUM(total_amount) as revenue")
            ->groupBy('bucket')->orderBy('bucket')->get()
            ->map(fn ($r) => ['bucket' => $r->bucket, 'orders' => (int) $r->orders, 'revenue' => round((float) $r->revenue, 2)])
            ->values();

        $byStatus = (clone $base)->selectRaw('status, COUNT(*) as c, SUM(total_amount) as t')
            ->groupBy('status')->get()
            ->map(fn ($r) => [
                // Eloquent still casts status on aggregate rows, so it may already be the enum.
                'status' => $status = $r->status instanceof OrderStatus ? $r->status->value : $r->status,
                'label' => OrderStatus::tryFrom($status)?->label() ?? $status,
                'orders' => (int) $r->c,
                'amount' => round((float) $r->t, 2),
            ])->sortByDesc('orders')->values();

        $soldIds = (clone $sold)->select('id');

        $topProducts = OrderItem::query()->whereIn('order_id', $soldIds)
            ->selectRaw('product_name, variant_name, SUM(quantity) as qty, SUM(quantity * unit_price) as revenue')
            ->groupBy('product_name', 'variant_name')->orderByDesc('revenue')->limit(15)->get()
            ->map(fn ($r) => ['product' => $r->product_name, 'variant' => $r->variant_name, 'quantity' => (int) $r->qty, 'revenue' => round((float) $r->revenue, 2)])
            ->values();

        $topCustomers = (clone $sold)->join('users', 'users.id', '=', 'orders.user_id')
            ->selectRaw('users.id, users.name, COUNT(orders.id) as orders, SUM(orders.total_amount) as revenue')
            ->groupBy('users.id', 'users.name')->orderByDesc('revenue')->limit(15)->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'orders' => (int) $r->orders, 'revenue' => round((float) $r->revenue, 2)])
            ->values();

        $byDelegate = (clone $sold)->whereNotNull('delegate_id')
            ->join('users', 'users.id', '=', 'orders.delegate_id')
            ->selectRaw('users.id, users.name, COUNT(orders.id) as orders, SUM(orders.total_amount) as revenue')
            ->groupBy('users.id', 'users.name')->orderByDesc('orders')->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'orders' => (int) $r->orders, 'revenue' => round((float) $r->revenue, 2)])
            ->values();

        $payments = Payment::query()->whereIn('order_id', $soldIds)->where('status', 'paid')
            ->selectRaw('method, COUNT(*) as c, SUM(amount) as t')->groupBy('method')->get()
            ->map(fn ($r) => ['method' => $r->method instanceof \BackedEnum ? $r->method->value : $r->method, 'count' => (int) $r->c, 'amount' => round((float) $r->t, 2)])
            ->values();
        $collected = round((float) $payments->sum('amount'), 2);

        return [
            'period' => $period->toArray(),
            'group_by' => $groupBy,
            'summary' => [
                'orders' => $ordersCount,
                'cancelled' => (clone $base)->where('status', OrderStatus::Cancelled->value)->count(),
                'revenue' => $revenue,
                'delivery_fees' => $deliveryFees,
                'avg_order' => $ordersCount > 0 ? round($revenue / $ordersCount, 2) : 0.0,
                'collected' => $collected,
                'outstanding' => round(max($revenue - $collected, 0), 2),
                'items_sold' => (int) OrderItem::query()->whereIn('order_id', $soldIds)->sum('quantity'),
            ],
            'series' => $series,
            'by_status' => $byStatus,
            'top_products' => $topProducts,
            'top_customers' => $topCustomers,
            'by_delegate' => $byDelegate,
            'payments' => $payments,
        ];
    }
}
