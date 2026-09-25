<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class StoreOpeningStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('opening_stocks.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'work_location_id' => ['required', 'integer', 'exists:work_locations,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.warehouse_location_id' => ['required', 'integer', 'exists:warehouse_locations,id'],
            'items.*.physical_qty' => ['required', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'work_location_id' => 'lokasi kerja',
            'items' => 'produk yang sudah dihitung',
            'items.*.product_id' => 'produk',
            'items.*.warehouse_location_id' => 'rak atau lokasi penyimpanan',
            'items.*.physical_qty' => 'jumlah fisik sekarang',
            'items.*.note' => 'catatan produk',
        ];
    }
}
