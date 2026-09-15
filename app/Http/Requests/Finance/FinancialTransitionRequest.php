<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance_annual.manage') === true || $this->user()?->can('finance_annual.approve') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['submit', 'lock', 'reopen', 'review', 'approve'])], 'reason' => ['nullable', 'string', 'min:5']];
    }

    public function attributes(): array
    {
        return ['action' => 'tindakan', 'reason' => 'alasan pembukaan kembali'];
    }
}
