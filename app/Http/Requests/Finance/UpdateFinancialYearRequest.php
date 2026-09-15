<?php

namespace App\Http\Requests\Finance;

use App\Enums\IncomeTaxScheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinancialYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance_annual.manage') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['legal_name' => ['required', 'string', 'max:255'], 'tax_number' => ['nullable', 'string', 'max:40'], 'company_address' => ['nullable', 'string'], 'report_city' => ['required', 'string', 'max:120'], 'commissioner_name' => ['required', 'string', 'max:255'], 'director_name' => ['required', 'string', 'max:255'], 'tax_scheme' => ['required', Rule::enum(IncomeTaxScheme::class)], 'tax_scheme_confirmed' => ['nullable', 'boolean'], 'fiscal_positive_adjustment' => ['required', 'numeric', 'min:0'], 'fiscal_negative_adjustment' => ['required', 'numeric', 'min:0'], 'tax_credit_amount' => ['required', 'numeric', 'min:0'], 'installment_amount' => ['required', 'numeric', 'min:0'], 'prior_payment_amount' => ['required', 'numeric', 'min:0'], 'manual_tax_amount' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string']];
    }

    public function attributes(): array
    {
        return ['legal_name' => 'nama badan', 'tax_number' => 'NPWP', 'report_city' => 'kota laporan', 'commissioner_name' => 'nama Komisaris', 'director_name' => 'nama Direktur', 'tax_scheme' => 'skema PPh', 'fiscal_positive_adjustment' => 'koreksi fiskal positif', 'fiscal_negative_adjustment' => 'koreksi fiskal negatif', 'tax_credit_amount' => 'kredit pajak', 'installment_amount' => 'angsuran pajak', 'prior_payment_amount' => 'pembayaran sebelumnya', 'manual_tax_amount' => 'nominal pajak manual'];
    }
}
