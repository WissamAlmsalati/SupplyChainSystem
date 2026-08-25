<?php

namespace Database\Seeders;

use App\Models\PremiumFeature;
use Illuminate\Database\Seeder;

class PremiumFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            ['code' => 'add_inventory', 'name' => 'إضافة مخزون', 'is_active' => false],
            ['code' => 'add_role', 'name' => 'إضافة دور', 'is_active' => false],
        ];

        foreach ($features as $feature) {
            PremiumFeature::firstOrCreate(['code' => $feature['code']], $feature);
        }
    }
}
