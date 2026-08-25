<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class AppUserFactory extends Factory
{
    protected $model = AppUser::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'mobile_number' => $this->faker->unique()->numerify('0##########'),
            'password_hash' => Hash::make('password'),
            'user_type_id' => UserType::factory(),
            'cafe_id' => null,
            'is_active' => true,
        ];
    }
}
