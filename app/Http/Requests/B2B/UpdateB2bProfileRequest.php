<?php

namespace App\Http\Requests\B2B;

use Illuminate\Foundation\Http\FormRequest;

class UpdateB2bProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('langganan_owner') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'addresses' => ['nullable', 'array'],
            'addresses.*.id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'addresses.*.label' => ['nullable', 'string', 'max:100'],
            'addresses.*.recipient_name' => ['nullable', 'string', 'max:255'],
            'addresses.*.phone_number' => ['nullable', 'string', 'max:50'],
            'addresses.*.address' => ['nullable', 'string', 'max:1000'],
            'addresses.*.city' => ['nullable', 'string', 'max:100'],
            'addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
            'addresses.*.directions' => ['nullable', 'string', 'max:1000'],
            'primary_address_index' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'business_name' => 'nama usaha',
            'pic_name' => 'PIC',
            'whatsapp_number' => 'nomor WhatsApp',
            'business_address' => 'alamat usaha',
            'addresses.*.label' => 'label alamat',
            'addresses.*.address' => 'alamat kirim',
        ];
    }
}
