<?php

namespace App\Http\Requests\Retail;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCashShiftClosingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cash_shifts.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'actual_cash_amount' => ['nullable', 'numeric', 'min:0'],
            'cash_counts' => ['nullable', 'array'],
            'cash_counts.*.denomination' => ['required_with:cash_counts', 'integer', 'min:1'],
            'cash_counts.*.quantity' => ['required_with:cash_counts', 'integer', 'min:0'],
            'discrepancy_reason' => ['nullable', 'string', 'max:1000'],
            'handover_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
