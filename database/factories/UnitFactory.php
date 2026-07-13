<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('UNIT-???')),
            'name' => $this->faker->word(),
            'symbol' => $this->faker->lexify('??'),
            'precision' => 0,
            'is_active' => true,
        ];
    }
}
