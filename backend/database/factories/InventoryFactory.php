<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

// Writes a balance row directly (no stock movement); seed real stock via StockService.
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'quantity' => $this->faker->numberBetween(0, 1000),
        ];
    }
}
