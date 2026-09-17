<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccessControlSeeder::class,
            UserSeeder::class,
            PremiumFeatureSeeder::class,
            LibyanDataSeeder::class,
            WalletSeeder::class,
            OrderSeeder::class,
            DemoContentSeeder::class,
        ]);
    }
}
