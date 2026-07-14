<?php

namespace App\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimulatePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('prices.view') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'unit_id' => ['nullable', Rule::exists('units', 'id')->where('is_active', true)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('is_active', true)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('is_active', true)],
            'channel' => ['required', 'in:retail,b2b,pos'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'requested_price' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
