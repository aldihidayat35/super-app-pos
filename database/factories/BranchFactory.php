<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'primary_warehouse_id' => Warehouse::factory(),
            'code' => strtoupper(fake()->unique()->bothify('TKO-###')),
            'name' => 'Toko '.fake()->city(),
            'address' => fake()->address(),
            'phone_number' => fake()->phoneNumber(),
            'manager_user_id' => User::factory(),
            'sales_target' => fake()->numberBetween(10000000, 100000000),
            'price_configuration' => 'standard',
            'closing_configuration' => 'daily',
            'is_closing_required' => true,
            'is_active' => true,
            'has_transactions' => false,
        ];
    }
}
