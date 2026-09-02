<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:32'],
            'nitku' => ['nullable', 'string', 'max:32'],
            'tax_address' => ['nullable', 'string', 'max:2000'],
            'is_pkp' => ['nullable', 'boolean'],
            'pkp_effective_date' => ['nullable', 'date'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_tax_number' => ['nullable', 'string', 'max:32'],
            'calculation_enabled' => ['nullable', 'boolean'],
            'default_output_tax_rule_id' => ['nullable', Rule::exists('tax_rules', 'id')->where(fn ($query) => $query->whereIn('direction', ['output', 'both']))],
            'default_input_tax_rule_id' => ['nullable', Rule::exists('tax_rules', 'id')->where(fn ($query) => $query->whereIn('direction', ['input', 'both']))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'legal_name' => 'nama legal perusahaan',
            'tax_number' => 'NPWP',
            'nitku' => 'NITKU',
            'tax_address' => 'alamat pajak',
            'pkp_effective_date' => 'tanggal efektif PKP',
            'default_output_tax_rule_id' => 'aturan pajak keluaran default',
            'default_input_tax_rule_id' => 'aturan pajak masukan default',
        ];
    }
}
