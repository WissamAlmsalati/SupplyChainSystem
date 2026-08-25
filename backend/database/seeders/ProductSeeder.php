<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        if (Category::count() === 0) {
            $this->call(CategorySeeder::class);
        }

        if (Supplier::count() === 0) {
            $this->call(SupplierSeeder::class);
        }

        $categories = Category::all();
        $suppliers = Supplier::all();

        Product::factory()
            ->count(20)
            ->create([
                'category_id' => fn () => $categories->random()->id,
                'supplier_id' => fn () => $suppliers->random()->id,
            ])
            ->each(function (Product $product) {
                ProductVariant::factory()->count(rand(1, 3))->create([
                    'product_id' => $product->id,
                ]);
            });
    }
}
