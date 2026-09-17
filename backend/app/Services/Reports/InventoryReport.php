<?php

namespace App\Services\Reports;

use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Warehouse;

// Stock on hand per warehouse and variant, low-stock lines, and the period's
// movements grouped by type.
class InventoryReport
{
    public const MOVEMENT_LABELS = [
        'purchase' => 'إدخال بضاعة',
        'sale' => 'خصم للطلب',
        'return' => 'إرجاع للمخزون',
        'adjustment' => 'تعديل',
    ];

    public function build(Period $period, ?int $warehouseId = null, int $lowStockAt = 10): array
    {
        $levels = Inventory::query()->with(['warehouse:id,name', 'productVariant.product:id,name'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderBy('warehouse_id')->get()
            ->map(fn ($i) => [
                'warehouse' => $i->warehouse?->name,
                'product' => $i->productVariant?->product?->name,
                'variant' => $i->productVariant?->name,
                'sku' => $i->productVariant?->sku,
                'quantity' => (int) $i->quantity,
                'price' => round((float) ($i->productVariant?->price ?? 0), 2),
                'value' => round((int) $i->quantity * (float) ($i->productVariant?->price ?? 0), 2),
            ])->values();

        $movements = StockMovement::query()->whereBetween('created_at', [$period->from, $period->to])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('type, COUNT(*) as c, SUM(CASE WHEN quantity_change > 0 THEN quantity_change ELSE 0 END) as inflow, SUM(CASE WHEN quantity_change < 0 THEN -quantity_change ELSE 0 END) as outflow')
            ->groupBy('type')->get()
            ->map(fn ($r) => [
                'type' => $r->type instanceof StockMovementType ? $r->type->value : $r->type,
                'label' => self::MOVEMENT_LABELS[$r->type instanceof StockMovementType ? $r->type->value : $r->type] ?? $r->type,
                'count' => (int) $r->c,
                'in' => (int) $r->inflow,
                'out' => (int) $r->outflow,
            ])->values();

        return [
            'period' => $period->toArray(),
            'warehouse' => $warehouseId ? Warehouse::find($warehouseId)?->only(['id', 'name']) : null,
            'low_stock_at' => $lowStockAt,
            'summary' => [
                'lines' => $levels->count(),
                'units' => (int) $levels->sum('quantity'),
                'value' => round((float) $levels->sum('value'), 2),
                'low_stock' => $levels->where('quantity', '<=', $lowStockAt)->count(),
                'out_of_stock' => $levels->where('quantity', '<=', 0)->count(),
            ],
            'levels' => $levels,
            'low_stock' => $levels->where('quantity', '<=', $lowStockAt)->values(),
            'movements' => $movements,
        ];
    }
}
