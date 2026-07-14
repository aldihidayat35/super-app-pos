<?php

namespace App\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('prices.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'exists:product_prices,id'],
            'product_id' => ['nullable', 'required_without:product_ids', Rule::exists('products', 'id')->where('status', 'active')],
            'product_ids' => ['nullable', 'array', 'required_without:product_id'],
            'product_ids.*' => [Rule::exists('products', 'id')->where('status', 'active')],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('is_active', true)],
            'channel' => ['required', 'in:all,retail,b2b,pos'],
            'price_ring' => ['required', 'string', 'max:60'],
            'customer_category' => ['nullable', 'string', 'max:60'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'recommended_price' => ['required', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_qty' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
