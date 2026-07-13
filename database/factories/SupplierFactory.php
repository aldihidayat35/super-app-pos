<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('SUP-###')),
            'name' => $this->faker->company(),
            'contact_name' => $this->faker->name(),
            'whatsapp_number' => '08'.$this->faker->numerify('##########'),
            'email' => $this->faker->safeEmail(),
            'city' => $this->faker->city(),
            'payment_term_days' => 30,
            'is_active' => true,
        ];
    }
}
