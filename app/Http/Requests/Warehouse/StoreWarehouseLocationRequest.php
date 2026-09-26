<?php

namespace App\Http\Requests\Warehouse;

use App\Enums\WarehouseLocationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('stock.create') || $this->user()?->can('retail.catalog.manage'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'owner_type' => ['nullable', 'in:warehouse,branch'],
            'warehouse_id' => ['nullable', 'required_if:owner_type,warehouse', 'required_without:branch_id', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'branch_id' => ['nullable', 'required_if:owner_type,branch', 'required_without:warehouse_id', Rule::exists('branches', 'id')->where('is_active', true)],
            'parent_id' => ['nullable', 'exists:warehouse_locations,id'],
            'type' => ['required', Rule::in(array_column(WarehouseLocationType::cases(), 'value'))],
            'code' => ['required', 'alpha_dash:ascii', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'item_type' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'owner_type' => 'tipe tempat',
            'warehouse_id' => 'gudang',
            'branch_id' => 'toko/cabang',
            'parent_id' => 'parent lokasi',
            'type' => 'tipe lokasi',
            'code' => 'kode',
            'name' => 'nama lokasi',
            'capacity' => 'kapasitas',
            'item_type' => 'jenis barang',
            'is_active' => 'status aktif',
        ];
    }
}
