<?php

namespace Database\Factories;

use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkLocation>
 */
class WorkLocationFactory extends Factory
{
    protected $model = WorkLocation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $type = fake()->randomElement(['warehouse', 'branch']);

        return [
            'type' => $type,
            'code' => strtoupper(fake()->unique()->bothify($type === 'warehouse' ? 'GDG-###' : 'TKO-###')),
            'name' => $type === 'warehouse' ? 'Gudang '.fake()->city() : 'Toko '.fake()->city(),
            'is_active' => true,
        ];
    }
}
