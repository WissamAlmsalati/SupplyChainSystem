<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        if (Category::count() === 0) {
            $this->call(CategorySeeder::class);
        }

        $categories = Category::all();

        Product::factory()
            ->count(20)
            ->create(['category_id' => fn () => $categories->random()->id])
            ->each(fn (Product $product) => ProductVariant::factory()->count(rand(1, 3))->create(['product_id' => $product->id]));
    }
}
