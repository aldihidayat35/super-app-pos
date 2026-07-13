<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper($this->faker->unique()->bothify('PRD-#####')),
            'name' => $this->faker->words(3, true),
            'category_id' => ProductCategory::factory(),
            'base_unit_id' => Unit::factory(),
            'status' => ProductStatus::ACTIVE,
            'minimum_order' => 1,
            'minimum_stock' => 0,
            'safety_stock' => 0,
            'total_stock' => 0,
            'cost_price' => 0,
            'minimum_price' => 0,
        ];
    }
}
