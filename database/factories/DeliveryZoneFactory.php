<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryZoneFactory extends Factory
{
    protected $model = DeliveryZone::class;

    public function definition(): array
    {
        return [
            'hex_id' => $this->faker->unique()->regexify('[A-F0-9]{10}'),
            'name' => $this->faker->city() . ' Zone',
            'delivery_price' => $this->faker->randomFloat(2, 5, 50),
            'is_active' => true,
        ];
    }
}
