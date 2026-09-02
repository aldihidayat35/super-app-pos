<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'compensation_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['compensation_amount' => 'kompensasi pajak masukan'];
    }
}
