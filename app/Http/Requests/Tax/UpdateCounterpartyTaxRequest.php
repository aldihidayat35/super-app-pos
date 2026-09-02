<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCounterpartyTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'party_type' => ['required', Rule::in(['customer', 'supplier'])],
            'party_id' => ['required', 'integer', 'min:1'],
            'tax_number' => ['nullable', 'string', 'max:32'],
            'tax_identity_type' => ['nullable', Rule::in(['npwp', 'nik', 'passport', 'other'])],
            'tax_address' => ['nullable', 'string', 'max:2000'],
            'is_pkp' => ['nullable', 'boolean'],
        ];
    }
}
