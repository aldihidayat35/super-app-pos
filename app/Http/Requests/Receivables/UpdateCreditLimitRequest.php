<?php

namespace App\Http\Requests\Receivables;

use App\Enums\CreditLimitStatus;
use App\Models\Receivable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreditLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageLimit', Receivable::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:365'],
            'approval_threshold_amount' => ['nullable', 'numeric', 'min:0'],
            'max_overdue_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['required', Rule::in(array_column(CreditLimitStatus::cases(), 'value'))],
            'blocked_reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
