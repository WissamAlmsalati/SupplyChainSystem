<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;

// Gross profit on goods for a period: what was sold, minus what came back,
// against what the goods cost when they were sold (order_items.unit_cost).
//
// - A restocked return undoes both the sale and its cost.
// - A damaged return undoes the sale but the cost stays: the goods are gone.
// - Delivery fees are income but not profit on goods, so they are shown apart.
// - A line sold before its cost was known cannot have a margin. It is counted
//   in revenue, left out of cost and profit, and reported as "uncosted" so the
//   margin is never flattered by a missing number.
// - A return belongs to its order's period, so a period's figures can still
//   move while its orders are being returned.
class ProfitReport
{
    public function build(Period $period): array
    {
        $sold = Order::query()
            ->whereBetween('placed_at', [$period->from, $period->to])
            ->where('status', '!=', OrderStatus::Cancelled->value);

        $groups = ['products' => [], 'categories' => [], 'customers' => [], 'cities' => []];
        $sum = ['units' => 0, 'revenue' => 0, 'costed_revenue' => 0, 'cost' => 0, 'returned_value' => 0, 'damaged_loss' => 0, 'uncosted_lines' => 0];

        OrderItem::query()
            ->whereIn('order_id', (clone $sold)->select('id'))
            ->with(['order:id,user_id,delivery_city', 'order.user:id,name', 'productVariant:id,product_id', 'productVariant.product:id,category_id', 'productVariant.product.category:id,name'])
            ->withSum(['returnItems as restocked' => fn ($q) => $q->where('condition', 'restock')], 'quantity')
            ->withSum(['returnItems as damaged' => fn ($q) => $q->where('condition', 'damaged')], 'quantity')
            ->lazyById(1000)
            ->each(function (OrderItem $item) use (&$groups, &$sum) {
                $cents = fn ($v) => (int) round((float) $v * 100);
                $restocked = (int) $item->restocked;
                $damaged = (int) $item->damaged;
                $netUnits = $item->quantity - $restocked - $damaged;
                $revenue = $netUnits * $cents($item->unit_price);
                $hasCost = $item->unit_cost !== null;
                $cost = $hasCost ? ($item->quantity - $restocked) * $cents($item->unit_cost) : 0;

                $sum['units'] += $netUnits;
                $sum['revenue'] += $revenue;
                $sum['returned_value'] += ($restocked + $damaged) * $cents($item->unit_price);
                if ($hasCost) {
                    $sum['costed_revenue'] += $revenue;
                    $sum['cost'] += $cost;
                    $sum['damaged_loss'] += $damaged * $cents($item->unit_cost);
                } else {
                    $sum['uncosted_lines']++;
                }

                $keys = [
                    'products' => [$item->product_name.'|'.$item->variant_name, ['product' => $item->product_name, 'variant' => $item->variant_name]],
                    'categories' => [(string) ($item->productVariant?->product?->category?->id ?? 0), ['name' => $item->productVariant?->product?->category?->name ?? 'بدون تصنيف']],
                    'customers' => [(string) $item->order->user_id, ['id' => $item->order->user_id, 'name' => $item->order->user?->name ?? '—']],
                    'cities' => [$item->order->delivery_city ?: '—', ['name' => $item->order->delivery_city ?: 'غير محدد']],
                ];

                foreach ($keys as $group => [$key, $label]) {
                    $row = $groups[$group][$key] ?? $label + ['units' => 0, 'revenue' => 0, 'costed_revenue' => 0, 'cost' => 0, 'cost_known' => true];
                    $row['units'] += $netUnits;
                    $row['revenue'] += $revenue;
                    if ($hasCost) {
                        $row['costed_revenue'] += $revenue;
                        $row['cost'] += $cost;
                    } else {
                        $row['cost_known'] = false;
                    }
                    $groups[$group][$key] = $row;
                }
            });

        $finish = function (array $rows, int $limit) {
            return collect($rows)->map(function (array $r) {
                $profit = $r['costed_revenue'] - $r['cost'];

                return array_diff_key($r, ['costed_revenue' => 0]) + [
                    'profit' => $profit / 100,
                    'margin_pct' => $r['costed_revenue'] > 0 ? round($profit * 100 / $r['costed_revenue'], 1) : null,
                ];
            })->map(fn ($r) => ['revenue' => $r['revenue'] / 100, 'cost' => $r['cost'] / 100] + $r)
                ->sortByDesc('profit')->take($limit)->values()->all();
        };

        $profit = $sum['costed_revenue'] - $sum['cost'];

        return [
            'period' => $period->toArray(),
            'summary' => [
                'orders' => (clone $sold)->count(),
                'units' => $sum['units'],
                'revenue' => $sum['revenue'] / 100,
                'cost' => $sum['cost'] / 100,
                'gross_profit' => $profit / 100,
                'margin_pct' => $sum['costed_revenue'] > 0 ? round($profit * 100 / $sum['costed_revenue'], 1) : null,
                'returned_value' => $sum['returned_value'] / 100,
                'damaged_loss' => $sum['damaged_loss'] / 100,
                'delivery_fees' => round((float) (clone $sold)->sum('delivery_fee'), 2),
                'uncosted_lines' => $sum['uncosted_lines'],
                'uncosted_revenue' => ($sum['revenue'] - $sum['costed_revenue']) / 100,
            ],
            'by_product' => $finish($groups['products'], 200),
            'by_category' => $finish($groups['categories'], 100),
            'by_customer' => $finish($groups['customers'], 100),
            'by_city' => $finish($groups['cities'], 100),
        ];
    }
}
