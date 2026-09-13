<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::factory()->count(3)->create();

        if (ProductVariant::count() === 0) {
            return;
        }

        $stock = app(StockService::class);
        $variants = ProductVariant::all();

        foreach (Warehouse::all() as $warehouse) {
            foreach ($variants->random(min(10, $variants->count())) as $variant) {
                $stock->adjust($warehouse->id, $variant->id, rand(10, 500), StockMovementType::Adjustment, null, 'بيانات تجريبية');
            }
        }
    }
}
