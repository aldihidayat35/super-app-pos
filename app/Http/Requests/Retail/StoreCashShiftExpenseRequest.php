<?php

namespace App\Http\Requests\Retail;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashShiftExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cash_shifts.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', 'in:plastic,transport,parking,operational,other'],
            'payment_method' => ['required', 'in:cash,bank_transfer,qris,manual'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'proof_path' => ['nullable', 'string', 'max:255'],
            'spent_at' => ['nullable', 'date'],
        ];
    }
}
