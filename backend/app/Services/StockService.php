<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// The only place inventories.quantity changes; every change writes a stock_movements row.
class StockService
{
    public function adjust(
        int $warehouseId,
        int $variantId,
        int $change,
        StockMovementType $type,
        ?Model $reference = null,
        ?string $note = null,
        array $details = [],
    ): Inventory {
        return DB::transaction(function () use ($warehouseId, $variantId, $change, $type, $reference, $note, $details) {
            $inventory = Inventory::where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first()
                ?? Inventory::create([
                    'warehouse_id' => $warehouseId,
                    'product_variant_id' => $variantId,
                    'quantity' => 0,
                ]);

            if ($inventory->quantity + $change < 0) {
                throw new InsufficientStockException([[
                    'product_variant_id' => $variantId,
                    'requested' => -$change,
                    'available' => $inventory->quantity,
                ]]);
            }

            if ($change !== 0) {
                $inventory->update(['quantity' => $inventory->quantity + $change]);
                $this->record($warehouseId, $variantId, $change, $type, $reference, $note, $details);
            }

            return $inventory;
        });
    }

    /**
     * Goods received into a warehouse (the only way stock enters the system).
     *
     * @param  array{unit_cost?:float|null, manufacturing_year?:int|null, expiry_date?:string|null}  $details
     */
    public function receive(int $warehouseId, int $variantId, int $quantity, array $details = [], ?string $note = null): Inventory
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Received quantity must be positive.');
        }

        return $this->adjust($warehouseId, $variantId, $quantity, StockMovementType::Purchase, null, $note, $details);
    }

    // Sets an absolute quantity (manual stock count) as an adjustment movement.
    public function setQuantity(Inventory $inventory, int $quantity, ?string $note = null): Inventory
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }

        return $this->adjust(
            $inventory->warehouse_id,
            $inventory->product_variant_id,
            $quantity - $inventory->quantity,
            StockMovementType::Adjustment,
            null,
            $note,
        );
    }

    /**
     * Takes the order's quantities from warehouse stock (warehouse order by id).
     * Must run inside the order's transaction; throws before changing anything
     * when any variant is short.
     *
     * @param  array<int, int>  $quantitiesByVariant
     */
    public function drainForOrder(Order $order, array $quantitiesByVariant): void
    {
        $rows = Inventory::whereIn('product_variant_id', array_keys($quantitiesByVariant))
            ->lockForUpdate()
            ->orderBy('warehouse_id')
            ->get()
            ->groupBy('product_variant_id');

        $shortages = [];
        foreach ($quantitiesByVariant as $variantId => $qty) {
            $available = (int) ($rows->get($variantId)?->sum('quantity') ?? 0);
            if ($available < $qty) {
                $shortages[] = [
                    'product_variant_id' => $variantId,
                    'requested' => $qty,
                    'available' => $available,
                ];
            }
        }

        if ($shortages) {
            throw new InsufficientStockException($shortages);
        }

        foreach ($quantitiesByVariant as $variantId => $qty) {
            foreach ($rows->get($variantId) ?? [] as $inventory) {
                if ($qty <= 0) {
                    break;
                }
                $take = min($inventory->quantity, $qty);
                if ($take === 0) {
                    continue;
                }
                $inventory->update(['quantity' => $inventory->quantity - $take]);
                $this->record($inventory->warehouse_id, $variantId, -$take, StockMovementType::Sale, $order);
                $qty -= $take;
            }
        }
    }

    // Puts back what drainForOrder took, to the same warehouses (e.g. on cancellation).
    public function restockOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $net = StockMovement::where('reference_type', $order->getMorphClass())
                ->where('reference_id', $order->id)
                ->whereIn('type', [StockMovementType::Sale->value, StockMovementType::Return->value])
                ->selectRaw('warehouse_id, product_variant_id, SUM(quantity_change) as net')
                ->groupBy('warehouse_id', 'product_variant_id')
                ->get();

            foreach ($net as $row) {
                if ((int) $row->net < 0) {
                    $this->adjust($row->warehouse_id, $row->product_variant_id, -(int) $row->net, StockMovementType::Return, $order);
                }
            }
        });
    }

    /**
     * Available stock per variant: total + per-warehouse breakdown.
     *
     * @return array<int, array{total:int, warehouses:Collection}>
     */
    public function levels(array $variantIds): array
    {
        return Inventory::whereIn('product_variant_id', $variantIds)
            ->with('warehouse:id,name')
            ->get()
            ->groupBy('product_variant_id')
            ->map(fn ($rows) => [
                'total' => (int) $rows->sum('quantity'),
                'warehouses' => $rows->map(fn ($r) => [
                    'warehouse_id' => $r->warehouse_id,
                    'warehouse_name' => $r->warehouse?->name,
                    'quantity' => (int) $r->quantity,
                ])->values(),
            ])
            ->all();
    }

    private function record(int $warehouseId, int $variantId, int $change, StockMovementType $type, ?Model $reference, ?string $note = null, array $details = []): void
    {
        StockMovement::create(array_intersect_key($details, array_flip(['unit_cost', 'manufacturing_year', 'expiry_date'])) + [
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'quantity_change' => $change,
            'type' => $type,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'created_by' => auth()->id(),
        ]);
    }
}
