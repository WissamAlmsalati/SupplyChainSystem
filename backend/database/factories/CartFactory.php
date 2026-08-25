<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\CafeBranch;
use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'user_id' => AppUser::factory(),
            'branch_id' => CafeBranch::factory(),
            'status' => $this->faker->randomElement(['active', 'abandoned', 'converted']),
        ];
    }
}
