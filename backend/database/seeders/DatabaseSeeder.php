<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserTypeSeeder::class,
            PermissionSeeder::class,
            UserSeeder::class,
            PremiumFeatureSeeder::class,
            LibyanDataSeeder::class,
            WalletSeeder::class,
            OrderSeeder::class,
            DemoContentSeeder::class,
        ]);
    }
}
