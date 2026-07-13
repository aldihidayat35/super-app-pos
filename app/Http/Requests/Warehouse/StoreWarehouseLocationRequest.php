<?php

namespace App\Http\Requests\Warehouse;

use App\Enums\WarehouseLocationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'parent_id' => ['nullable', 'exists:warehouse_locations,id'],
            'type' => ['required', Rule::in(array_column(WarehouseLocationType::cases(), 'value'))],
            'code' => ['required', 'alpha_dash:ascii', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'item_type' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
