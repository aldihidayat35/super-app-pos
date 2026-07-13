<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'source_work_location_id' => ['required', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'source_warehouse_location_id' => ['nullable', 'exists:warehouse_locations,id'],
            'destination_work_location_id' => ['required', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'destination_warehouse_location_id' => ['nullable', 'different:source_warehouse_location_id', 'exists:warehouse_locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }
}
