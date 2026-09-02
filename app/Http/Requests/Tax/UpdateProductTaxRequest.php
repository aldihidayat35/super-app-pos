<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tax_rule_id' => ['nullable', 'exists:tax_rules,id'],
            'tax_category_code' => ['nullable', 'string', 'max:60'],
            'is_taxable' => ['nullable', 'boolean'],
        ];
    }
}
