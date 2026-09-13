<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\AppUser;
use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'user_id' => AppUser::factory()->customer(),
            'name' => $this->faker->streetName(),
            'city' => $this->faker->city(),
            'street' => $this->faker->streetAddress(),
            'contact_phones' => [$this->faker->numerify('09########')],
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'delivery_zone_id' => DeliveryZone::factory(),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
