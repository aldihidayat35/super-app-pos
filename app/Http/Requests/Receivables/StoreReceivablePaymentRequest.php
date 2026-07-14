<?php

namespace App\Http\Requests\Receivables;

use App\Enums\PaymentMethod;
use App\Models\Receivable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceivablePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pay', Receivable::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'method' => ['required', Rule::in(array_keys(PaymentMethod::options()))],
            'payment_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
