<?php

namespace App\Http\Requests\Retail;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pos.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('is_active', true)],
            'cart_snapshot' => ['required', 'array', 'min:1'],
            'estimated_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
