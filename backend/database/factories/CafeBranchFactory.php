<?php

namespace Database\Factories;

use App\Models\Cafe;
use App\Models\CafeBranch;
use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

class CafeBranchFactory extends Factory
{
    protected $model = CafeBranch::class;

    public function definition(): array
    {
        return [
            'cafe_id' => Cafe::factory(),
            'name' => $this->faker->streetName(),
            'city' => $this->faker->city(),
            'street' => $this->faker->streetAddress(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'delivery_zone_id' => DeliveryZone::factory(),
            'is_active' => true,
        ];
    }
}
