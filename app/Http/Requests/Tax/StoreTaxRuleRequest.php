<?php

namespace App\Http\Requests\Tax;

use App\Enums\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:tax_rules,code'],
            'name' => ['required', 'string', 'max:255'],
            'tax_type' => ['required', Rule::enum(TaxType::class)],
            'direction' => ['required', Rule::in(['output', 'input', 'both'])],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'dpp_factor' => ['required', 'numeric', 'gt:0', 'max:1', 'decimal:0,8'],
            'luxury_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'is_creditable' => ['nullable', 'boolean'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode aturan',
            'name' => 'nama aturan',
            'tax_type' => 'jenis pajak',
            'direction' => 'arah transaksi',
            'rate' => 'tarif',
            'dpp_factor' => 'faktor DPP',
            'luxury_tax_rate' => 'tarif PPnBM',
            'effective_from' => 'tanggal mulai berlaku',
            'effective_until' => 'tanggal akhir berlaku',
        ];
    }
}
