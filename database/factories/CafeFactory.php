<?php

namespace Database\Factories;

use App\Models\Cafe;
use Illuminate\Database\Eloquent\Factories\Factory;

class CafeFactory extends Factory
{
    protected $model = Cafe::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Cafe',
            'contact_info' => $this->faker->phoneNumber(),
            'created_by_admin_id' => null,
            'is_active' => $this->faker->boolean(90),
        ];
    }
}
