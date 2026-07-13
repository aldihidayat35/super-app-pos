<?php

namespace Database\Factories;

use App\Models\ProductBrand;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductBrand> */
class ProductBrandFactory extends Factory
{
    protected $model = ProductBrand::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('BR-###')),
            'name' => $this->faker->company(),
            'is_active' => true,
        ];
    }
}
