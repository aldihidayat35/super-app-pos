<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('GDG-###')),
            'name' => 'Gudang '.fake()->city(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'phone_number' => fake()->phoneNumber(),
            'manager_user_id' => User::factory(),
            'capacity' => fake()->numberBetween(100, 10000),
            'service_area' => fake()->city(),
            'is_active' => true,
            'has_transactions' => false,
        ];
    }
}
