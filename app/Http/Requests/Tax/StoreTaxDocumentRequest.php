<?php

namespace App\Http\Requests\Tax;

use App\Enums\TaxDirection;
use App\Enums\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(TaxDirection::class)],
            'tax_type' => ['required', Rule::enum(TaxType::class)],
            'tax_rule_id' => ['nullable', 'exists:tax_rules,id'],
            'original_document_id' => ['nullable', 'exists:tax_documents,id'],
            'work_location_id' => ['nullable', 'exists:work_locations,id'],
            'document_type' => ['required', Rule::in(['tax_invoice', 'supplier_invoice', 'withholding_receipt', 'credit_note', 'return_note', 'other'])],
            'document_number' => ['required', 'string', 'max:120'],
            'counterparty_type' => ['nullable', Rule::in(['customer', 'supplier', 'employee', 'other'])],
            'counterparty_name' => ['required', 'string', 'max:255'],
            'counterparty_tax_number' => ['nullable', 'string', 'max:32'],
            'counterparty_address' => ['nullable', 'string', 'max:2000'],
            'issue_date' => ['required', 'date'],
            'tax_date' => ['required', 'date'],
            'dpp_amount' => ['required', 'numeric'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'dpp_factor' => ['nullable', 'numeric', 'gt:0', 'max:1', 'decimal:0,8'],
            'tax_amount' => ['nullable', 'numeric'],
            'luxury_tax_amount' => ['nullable', 'numeric'],
            'withholding_tax_amount' => ['nullable', 'numeric'],
            'is_creditable' => ['nullable', 'boolean'],
            'coretax_reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'direction' => 'arah pajak',
            'tax_type' => 'jenis pajak',
            'document_type' => 'jenis dokumen',
            'document_number' => 'nomor dokumen',
            'counterparty_name' => 'nama lawan transaksi',
            'issue_date' => 'tanggal dokumen',
            'tax_date' => 'tanggal pajak',
            'dpp_amount' => 'DPP',
            'tax_rate' => 'tarif pajak',
        ];
    }
}
