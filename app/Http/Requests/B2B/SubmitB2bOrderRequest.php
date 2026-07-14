<?php

namespace App\Http\Requests\B2B;

use Illuminate\Foundation\Http\FormRequest;

class SubmitB2bOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('b2b_orders.create') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'customer_address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'delivery_method' => ['required', 'in:courier,pickup,expedition'],
            'courier_name' => ['nullable', 'string', 'max:120'],
            'payment_preference' => ['required', 'in:cash,transfer,credit'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'terms_accepted' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_address_id' => 'alamat pengiriman',
            'requested_delivery_date' => 'tanggal kirim diminta',
            'delivery_method' => 'metode pengiriman',
            'courier_name' => 'kurir/ekspedisi',
            'payment_preference' => 'preferensi pembayaran',
            'notes' => 'catatan',
            'terms_accepted' => 'persetujuan syarat',
        ];
    }
}
