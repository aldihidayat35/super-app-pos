<?php

namespace App\Http\Requests\Retail;

use Illuminate\Foundation\Http\FormRequest;

class ApproveCashShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cash_shifts.approve') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['notes' => ['nullable', 'string', 'max:1000']];
    }
}
