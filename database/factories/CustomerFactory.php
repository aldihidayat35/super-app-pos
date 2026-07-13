<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'type' => CustomerType::B2B,
            'code' => strtoupper($this->faker->unique()->bothify('CUS-###')),
            'business_name' => $this->faker->company(),
            'owner_name' => $this->faker->name(),
            'pic_name' => $this->faker->name(),
            'whatsapp_number' => '08'.$this->faker->numerify('##########'),
            'email' => $this->faker->safeEmail(),
            'city' => $this->faker->city(),
            'price_category' => 'grosir',
            'minimum_order' => 0,
            'payment_term_days' => 14,
            'credit_limit' => 5000000,
            'receivable_balance' => 0,
            'verification_status' => CustomerStatus::ACTIVE,
            'account_status' => CustomerStatus::ACTIVE,
            'is_active' => true,
        ];
    }
}
