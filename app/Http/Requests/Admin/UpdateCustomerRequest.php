<?php

namespace App\Http\Requests\Admin;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sales_user_id' => [
                'nullable',
                'integer',
                Rule::prohibitedIf(! ($this->user()?->can('sales.customers.assign') ?? false)),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && ! User::query()->whereKey((int) $value)->role('sales')->exists()) {
                        $fail('Pengguna yang dipilih tidak memiliki role Sales.');
                    }
                },
            ],
            'type' => ['required', Rule::enum(CustomerType::class)],
            'code' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('customers', 'code')->ignore($this->route('customer'))],
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'regex:/^\+?[0-9\s-]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'price_category' => ['required', 'string', 'max:60'],
            'minimum_order' => ['required', 'numeric', 'min:0'],
            'payment_term_days' => ['required', 'integer', 'min:0', 'max:365'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'verification_status' => ['required', Rule::enum(CustomerStatus::class)],
            'account_status' => ['required', Rule::enum(CustomerStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }
}
