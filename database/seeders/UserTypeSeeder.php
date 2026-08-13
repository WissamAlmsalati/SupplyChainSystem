<?php

namespace Database\Seeders;

use App\Models\UserType;
use Illuminate\Database\Seeder;

class UserTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin', 'admin', 'cafe', 'delegate'] as $name) {
            UserType::firstOrCreate(['name' => $name]);
        }
    }
}
