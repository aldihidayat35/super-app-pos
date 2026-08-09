<?php

namespace App\Http\Requests\Retail;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertRestockRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_transfers.approve') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'source_warehouse_location_id' => ['required', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity_transfer' => ['required', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'source_warehouse_id' => 'gudang sumber',
            'source_warehouse_location_id' => 'lokasi ambil',
            'items.*.quantity_transfer' => 'qty transfer',
        ];
    }
}
