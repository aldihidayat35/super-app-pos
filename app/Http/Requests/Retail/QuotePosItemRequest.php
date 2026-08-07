<?php

namespace App\Http\Requests\Retail;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotePosItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pos.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('is_active', true)],
            'unit_id' => ['nullable', Rule::exists('units', 'id')->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'selected_price' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'product_id' => 'produk',
            'customer_id' => 'pelanggan',
            'unit_id' => 'unit jual',
            'quantity' => 'qty',
            'selected_price' => 'harga jual',
            'discount_percent' => 'diskon',
        ];
    }
}
