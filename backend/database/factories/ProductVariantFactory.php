<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => $this->faker->randomElement(['صغير', 'وسط', 'كبير', '250 جم', '500 جم', '1 كجم']),
            'sku' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{6}'),
            'price' => $this->faker->randomFloat(2, 1, 500),
            'cost_price' => null,
            'is_active' => true,
        ];
    }
}
