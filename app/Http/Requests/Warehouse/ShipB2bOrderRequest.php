<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class ShipB2bOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('b2b_orders.approve') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'courier_name' => ['nullable', 'string', 'max:120'],
            'internal_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['courier_name' => 'kurir/ekspedisi', 'internal_note' => 'catatan internal'];
    }
}
