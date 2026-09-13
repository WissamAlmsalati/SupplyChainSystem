<?php

namespace Database\Factories;

use App\Enums\UserRole;
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
            'mobile_number' => $this->faker->unique()->numerify('09########'),
            'password' => Hash::make('password'),
            'user_type_id' => UserType::factory(),
            'is_active' => true,
        ];
    }

    public function role(UserRole $role): static
    {
        return $this->state(fn () => [
            'user_type_id' => UserType::firstOrCreate(['name' => $role->value])->id,
        ]);
    }

    public function customer(): static
    {
        return $this->role(UserRole::Customer);
    }

    public function delegate(): static
    {
        return $this->role(UserRole::Delegate);
    }

    public function admin(): static
    {
        return $this->role(UserRole::Admin);
    }
}
