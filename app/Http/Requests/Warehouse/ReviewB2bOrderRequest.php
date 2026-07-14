<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class ReviewB2bOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('b2b_orders.approve') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'approved_quantities' => ['nullable', 'array'],
            'approved_quantities.*' => ['nullable', 'numeric', 'gt:0'],
            'allow_partial' => ['nullable', 'boolean'],
            'reservation_expires_at' => ['nullable', 'date', 'after:now'],
            'shipping_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'internal_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'approved_quantities.*' => 'qty disetujui',
            'allow_partial' => 'izinkan parsial',
            'reservation_expires_at' => 'expiry reservation',
            'shipping_cost_amount' => 'biaya kirim',
            'internal_note' => 'catatan internal',
        ];
    }
}
