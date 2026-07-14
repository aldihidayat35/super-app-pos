<?php

namespace App\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('prices.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'exists:price_rules,id'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:all,retail,b2b,pos'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('is_active', true)],
            'customer_category' => ['nullable', 'string', 'max:60'],
            'margin_method' => ['required', 'in:percent,nominal'],
            'minimum_margin_percent' => ['nullable', 'numeric', 'min:0'],
            'minimum_margin_amount' => ['nullable', 'numeric', 'min:0'],
            'overpricing_tolerance_percent' => ['nullable', 'numeric', 'min:0'],
            'max_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'approval_threshold_amount' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
