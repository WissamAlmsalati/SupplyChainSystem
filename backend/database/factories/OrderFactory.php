<?php

namespace Database\Factories;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\AppUser;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

// Bare order row without items or stock movements; use OrderPlacementService for real orders.
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 10, 500);
        $fee = $this->faker->randomFloat(2, 0, 30);

        return [
            'user_id' => AppUser::factory()->customer(),
            'status' => OrderStatus::Pending,
            'source' => OrderSource::App,
            'delivery_address_name' => $this->faker->streetName(),
            'delivery_city' => $this->faker->city(),
            'delivery_latitude' => $this->faker->latitude(),
            'delivery_longitude' => $this->faker->longitude(),
            'subtotal' => $subtotal,
            'delivery_fee' => $fee,
            'total_amount' => $subtotal + $fee,
            'placed_at' => now(),
        ];
    }
}
