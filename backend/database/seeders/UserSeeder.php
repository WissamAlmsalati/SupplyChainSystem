<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AppUser;
use App\Models\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $staff = [
            ['email' => 'superadmin@example.com', 'name' => 'Super Admin', 'mobile' => '0900000001', 'role' => UserRole::SuperAdmin],
            ['email' => 'admin@example.com', 'name' => 'Admin', 'mobile' => '0900000002', 'role' => UserRole::Admin],
        ];

        foreach ($staff as $row) {
            AppUser::firstOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'mobile_number' => $row['mobile'],
                    'password' => Hash::make('password'),
                    'user_type_id' => UserType::where('name', $row['role']->value)->value('id'),
                    'is_active' => true,
                ]
            );
        }
    }
}
