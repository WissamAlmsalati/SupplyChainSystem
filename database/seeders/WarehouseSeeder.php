<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::factory()->count(3)->create();

        if (ProductVariant::count() === 0) {
            return;
        }

        $warehouses = Warehouse::all();
        $variants = ProductVariant::all();

        foreach ($warehouses as $warehouse) {
            foreach ($variants->random(min(10, $variants->count())) as $variant) {
                Inventory::factory()->create([
                    'warehouse_id' => $warehouse->id,
                    'product_variant_id' => $variant->id,
                ]);
            }
        }
    }
}
