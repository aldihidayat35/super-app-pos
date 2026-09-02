<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.orders.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'customer_address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'delivery_method' => ['required', 'in:courier,pickup,expedition'],
            'courier_name' => ['nullable', 'string', 'max:120'],
            'payment_preference' => ['required', 'in:cash,transfer,credit'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms_accepted' => ['accepted'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'customer_address_id' => 'alamat pengiriman',
            'requested_delivery_date' => 'tanggal pengiriman',
            'delivery_method' => 'metode pengiriman',
            'payment_preference' => 'metode pembayaran',
            'terms_accepted' => 'persetujuan syarat',
            'items' => 'item order',
            'items.*.product_id' => 'produk',
            'items.*.quantity' => 'jumlah',
        ];
    }
}
