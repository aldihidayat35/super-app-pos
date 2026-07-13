<?php

namespace App\Http\Requests\Admin;

use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSettings', $this->route('customer')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'verification_status' => ['required', Rule::enum(CustomerStatus::class)],
            'account_status' => ['required', Rule::enum(CustomerStatus::class)],
            'price_category' => ['required', 'string', 'max:60'],
            'minimum_order' => ['required', 'numeric', 'min:0'],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:365'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'status_reason' => ['required_if:account_status,frozen,blacklisted', 'nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
            'document_type' => ['nullable', 'string', 'max:60'],
            'document_name' => ['nullable', 'string', 'max:255'],
            'price_overrides' => ['array'],
            'price_overrides.*.product_id' => ['nullable', 'exists:products,id'],
            'price_overrides.*.price' => ['nullable', 'numeric', 'min:0'],
            'price_overrides.*.starts_at' => ['nullable', 'date'],
            'price_overrides.*.ends_at' => ['nullable', 'date', 'after_or_equal:price_overrides.*.starts_at'],
            'price_overrides.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
