<?php

namespace Database\Factories;

use App\Enums\CartType;
use App\Models\AppUser;
use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'user_id' => AppUser::factory()->customer(),
            'type' => CartType::Shopping,
            'name' => null,
        ];
    }

    public function recurring(?string $name = null): static
    {
        return $this->state(fn () => [
            'type' => CartType::Recurring,
            'name' => $name ?? $this->faker->words(2, true),
        ]);
    }
}
