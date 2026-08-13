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
            'sku' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{6}'),
            'attribute_name' => $this->faker->randomElement(['Size', 'Weight', 'Color', null]),
            'attribute_value' => $this->faker->word(),
            'price' => $this->faker->randomFloat(2, 1, 500),
            'is_active' => true,
        ];
    }
}
