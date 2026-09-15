<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance_annual.manage') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['year' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:financial_years,year'], 'legal_name' => ['required', 'string', 'max:255'], 'is_historical' => ['nullable', 'boolean']];
    }

    public function attributes(): array
    {
        return ['year' => 'tahun', 'legal_name' => 'nama badan', 'is_historical' => 'mode data historis'];
    }
}
