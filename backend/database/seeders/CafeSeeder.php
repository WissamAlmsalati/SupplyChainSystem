<?php

namespace Database\Seeders;

use App\Models\AppUser;
use App\Models\Cafe;
use App\Models\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CafeSeeder extends Seeder
{
    public function run(): void
    {
        $adminType = UserType::where('name', 'admin')->first();
        $cafeType = UserType::where('name', 'cafe')->first();

        $admin = AppUser::factory()->create([
            'name' => 'Default Admin',
            'email' => 'admin@example.com',
            'user_type_id' => $adminType?->id,
            'password_hash' => Hash::make('password'),
        ]);

        Cafe::factory()
            ->count(5)
            ->create([
                'created_by_admin_id' => $admin->id,
            ])
            ->each(function (Cafe $cafe) use ($cafeType) {
                AppUser::factory()->count(2)->create([
                    'user_type_id' => $cafeType?->id,
                    'password_hash' => Hash::make('password'),
                ])->each(fn (AppUser $user) => $user->syncCafeUser(['cafe_id' => $cafe->id]));
            });
    }
}
