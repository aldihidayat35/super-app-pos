<?php

namespace App\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpecialPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('prices.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('is_active', true)],
            'product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('is_active', true)],
            'channel' => ['required', 'in:all,retail,b2b,pos'],
            'price' => ['required', 'numeric', 'min:0'],
            'minimum_qty' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'reason' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
