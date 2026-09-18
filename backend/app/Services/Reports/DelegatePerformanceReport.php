<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Models\AppUser;
use App\Models\DelegateSettlement;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use Illuminate\Support\Carbon;

// How each delegate did in a period. Orders are counted by when they were
// placed; times come from the status log, so they are what really happened
// rather than what the order row says now.
//
// - success rate is over orders that reached an end (delivered or cancelled);
//   orders still on the road do not count against anyone.
// - delivery time runs from "out for delivery" to "delivered"; total time from
//   the order being placed to delivered.
// - custody figures are today's, not the period's: they answer "who is holding
//   cash right now, and for how long".
class DelegatePerformanceReport
{
    public function build(Period $period): array
    {
        $delegates = AppUser::query()
            ->whereHas('userType', fn ($q) => $q->where('name', 'delegate'))
            ->with('delegateProfile')
            ->orderBy('name')
            ->get();

        $orders = Order::query()
            ->whereNotNull('delegate_id')
            ->whereBetween('placed_at', [$period->from, $period->to])
            ->get(['id', 'delegate_id', 'status', 'total_amount', 'placed_at']);

        $logs = OrderStatusLog::query()
            ->whereIn('order_id', $orders->pluck('id'))
            ->whereIn('to_status', [OrderStatus::OutForDelivery->value, OrderStatus::Delivered->value])
            ->orderBy('id')
            ->get(['order_id', 'to_status', 'created_at'])
            ->groupBy('order_id');

        $cash = Payment::query()
            ->whereNotNull('collected_by')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->selectRaw('collected_by, SUM(amount) as total')
            ->groupBy('collected_by')
            ->pluck('total', 'collected_by');

        $lastSettlement = DelegateSettlement::query()
            ->selectRaw('delegate_id, MAX(created_at) as last_at')
            ->groupBy('delegate_id')
            ->pluck('last_at', 'delegate_id');

        $done = [OrderStatus::Delivered, OrderStatus::Received];

        $rows = $delegates->map(function (AppUser $delegate) use ($orders, $logs, $cash, $lastSettlement, $done) {
            $mine = $orders->where('delegate_id', $delegate->id);
            $delivered = $mine->filter(fn ($o) => in_array($o->status, $done, true));
            $cancelled = $mine->filter(fn ($o) => $o->status === OrderStatus::Cancelled);

            $onRoad = [];
            $endToEnd = [];
            foreach ($delivered as $order) {
                $orderLogs = $logs->get($order->id) ?? collect();
                $out = $orderLogs->firstWhere('to_status', OrderStatus::OutForDelivery->value)?->created_at;
                $arrived = $orderLogs->firstWhere('to_status', OrderStatus::Delivered->value)?->created_at;
                if ($out && $arrived) {
                    $onRoad[] = $out->diffInMinutes($arrived);
                }
                if ($arrived) {
                    $endToEnd[] = $order->placed_at->diffInMinutes($arrived);
                }
            }

            $finished = $delivered->count() + $cancelled->count();
            $settledAt = $lastSettlement[$delegate->id] ?? null;
            $custody = (float) ($delegate->delegateProfile?->custody_balance ?? 0);

            return [
                'id' => $delegate->id,
                'name' => $delegate->name,
                'mobile_number' => $delegate->mobile_number,
                'is_active' => (bool) $delegate->is_active,
                'is_available' => (bool) $delegate->delegateProfile?->is_available,
                'assigned' => $mine->count(),
                'delivered' => $delivered->count(),
                'cancelled' => $cancelled->count(),
                'in_progress' => $mine->count() - $finished,
                'success_rate' => $finished > 0 ? round($delivered->count() * 100 / $finished, 1) : null,
                'avg_delivery_minutes' => $onRoad ? (int) round(array_sum($onRoad) / count($onRoad)) : null,
                'avg_total_minutes' => $endToEnd ? (int) round(array_sum($endToEnd) / count($endToEnd)) : null,
                'delivered_value' => round((float) $delivered->sum('total_amount'), 2),
                'cash_collected' => round((float) ($cash[$delegate->id] ?? 0), 2),
                'custody_balance' => round($custody, 2),
                'last_settlement_at' => $settledAt ? (string) $settledAt : null,
                // Only meaningful while cash is being held.
                'days_since_settlement' => $custody > 0 && $settledAt ? (int) Carbon::parse($settledAt)->diffInDays(now()) : null,
            ];
        })->sortByDesc('delivered')->values();

        $timed = $rows->whereNotNull('avg_delivery_minutes');
        $finishedAll = $rows->sum('delivered') + $rows->sum('cancelled');

        return [
            'period' => $period->toArray(),
            'summary' => [
                'delegates' => $rows->count(),
                'active_in_period' => $rows->where('assigned', '>', 0)->count(),
                'assigned' => $rows->sum('assigned'),
                'delivered' => $rows->sum('delivered'),
                'cancelled' => $rows->sum('cancelled'),
                'success_rate' => $finishedAll > 0 ? round($rows->sum('delivered') * 100 / $finishedAll, 1) : null,
                // Weighted by deliveries, so one quick run does not move the average.
                'avg_delivery_minutes' => $timed->sum('delivered') > 0
                    ? (int) round($timed->sum(fn ($r) => $r['avg_delivery_minutes'] * $r['delivered']) / $timed->sum('delivered'))
                    : null,
                'cash_collected' => round($rows->sum('cash_collected'), 2),
                'custody_held' => round($rows->sum('custody_balance'), 2),
            ],
            'delegates' => $rows->all(),
        ];
    }
}
