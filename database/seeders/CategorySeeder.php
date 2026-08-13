<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $beverages = Category::create(['name' => 'Beverages']);
        $food = Category::create(['name' => 'Food']);

        Category::create(['name' => 'Coffee', 'parent_category_id' => $beverages->id]);
        Category::create(['name' => 'Tea', 'parent_category_id' => $beverages->id]);
        Category::create(['name' => 'Pastries', 'parent_category_id' => $food->id]);
        Category::create(['name' => 'Sandwiches', 'parent_category_id' => $food->id]);
    }
}
