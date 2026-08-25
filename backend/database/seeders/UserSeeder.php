<?php

namespace Database\Seeders;

use App\Models\AppUser;
use App\Models\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminType = UserType::where('name', 'admin')->first();

        AppUser::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'mobile_number' => '0911111111',
                'password_hash' => Hash::make('password'),
                'user_type_id' => $adminType?->id,
                'is_active' => true,
            ]
        );
    }
}
