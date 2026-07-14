<?php

namespace App\Http\Requests\Retail;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cash_shifts.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true)],
            'terminal_code' => ['nullable', 'string', 'max:80'],
            'opening_cash_amount' => ['required', 'numeric', 'min:0'],
            'discrepancy_threshold_amount' => ['nullable', 'numeric', 'min:0'],
            'attendance_override_reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
