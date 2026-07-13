<?php

namespace Database\Factories;

use App\Enums\WarehouseLocationType;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WarehouseLocation> */
class WarehouseLocationFactory extends Factory
{
    protected $model = WarehouseLocation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->bothify('BIN-###'));

        return [
            'warehouse_id' => Warehouse::factory(),
            'type' => WarehouseLocationType::BIN,
            'code' => $code,
            'full_code' => $code,
            'name' => 'Bin '.fake()->bothify('###'),
            'capacity' => 100,
            'is_active' => true,
        ];
    }
}
