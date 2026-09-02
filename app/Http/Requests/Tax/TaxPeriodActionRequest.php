<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class TaxPeriodActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tax.approve') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:review,approve,report,pay,lock,reopen'],
            'filing_reference' => ['required_if:action,report', 'nullable', 'string', 'max:120'],
            'payment_reference' => ['required_if:action,pay', 'nullable', 'string', 'max:120'],
            'notes' => ['required_if:action,reopen', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'filing_reference' => 'referensi pelaporan',
            'payment_reference' => 'referensi pembayaran',
            'notes' => 'alasan pembukaan kembali',
        ];
    }
}
