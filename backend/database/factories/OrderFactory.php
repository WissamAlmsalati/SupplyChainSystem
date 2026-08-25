<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\CafeBranch;
use App\Models\DeliveryZone;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => AppUser::factory(),
            'branch_id' => CafeBranch::factory(),
            'delegate_id' => null,
            'delivery_zone_id' => DeliveryZone::factory(),
            'delivery_fee' => $this->faker->randomFloat(2, 0, 30),
            'order_date' => now(),
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'shipped', 'delivered', 'cancelled']),
            'total_amount' => $this->faker->randomFloat(2, 10, 1000),
        ];
    }
}
