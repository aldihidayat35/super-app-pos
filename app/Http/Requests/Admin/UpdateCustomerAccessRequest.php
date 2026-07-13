<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerAccessRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $addresses = $this->input('addresses', []);
        $users = $this->input('users', []);
        $addresses = is_array($addresses) ? $addresses : [];
        $users = is_array($users) ? $users : [];

        $this->merge([
            'addresses' => array_values(array_filter($addresses, fn (mixed $address): bool => is_array($address) && (filled($address['id'] ?? null) || filled($address['label'] ?? null) || filled($address['address'] ?? null)))),
            'users' => array_values(array_filter($users, fn (mixed $user): bool => is_array($user) && (filled($user['id'] ?? null) || filled($user['name'] ?? null) || filled($user['username'] ?? null) || filled($user['email'] ?? null)))),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manageAccess', $this->route('customer')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'addresses' => ['array'],
            'addresses.*.id' => ['nullable', 'exists:customer_addresses,id'],
            'addresses.*.label' => ['required', 'string', 'max:100'],
            'addresses.*.recipient_name' => ['nullable', 'string', 'max:255'],
            'addresses.*.phone_number' => ['nullable', 'string', 'max:50'],
            'addresses.*.address' => ['required', 'string', 'max:2000'],
            'addresses.*.city' => ['nullable', 'string', 'max:100'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
            'addresses.*.directions' => ['nullable', 'string', 'max:1000'],
            'primary_address_index' => ['nullable', 'integer', 'min:0'],
            'users' => ['array'],
            'users.*.id' => ['nullable', 'exists:users,id'],
            'users.*.name' => ['required_without:users.*.id', 'nullable', 'string', 'max:255'],
            'users.*.username' => ['required_without:users.*.id', 'nullable', 'string', 'max:100', 'alpha_dash'],
            'users.*.email' => ['required_without:users.*.id', 'nullable', 'email', 'max:255'],
            'users.*.role' => ['required', Rule::in(['langganan_owner', 'langganan_staff'])],
            'users.*.is_active' => ['boolean'],
            'users.*.blocked_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
