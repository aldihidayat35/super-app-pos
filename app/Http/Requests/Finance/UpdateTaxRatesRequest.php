<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance_annual.view') === true
            || $this->user()?->can('finance_annual.manage') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'final_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'facility_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'general_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'small_turnover_limit' => ['required', 'numeric', 'min:0'],
            'facility_turnover_limit' => ['required', 'numeric', 'min:0'],
            'manual_tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'final_rate' => 'tarif PPh final',
            'facility_rate' => 'tarif fasilitas Pasal 31E',
            'general_rate' => 'tarif umum PPh',
            'small_turnover_limit' => 'batas omzet fasilitas penuh',
            'facility_turnover_limit' => 'batas omzet fasilitas Pasal 31E',
            'manual_tax_amount' => 'nominal pajak manual',
            'notes' => 'catatan alasan perubahan',
        ];
    }
}
