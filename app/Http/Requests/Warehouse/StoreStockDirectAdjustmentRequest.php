<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockDirectAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_direct_adjustments.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'work_location_id' => ['required', 'integer', 'exists:work_locations,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.stock_id' => ['required', 'integer', 'distinct', 'exists:stocks,id'],
            'items.*.physical_qty' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost_snapshot' => ['required', 'numeric', 'gt:0'],
            'items.*.sale_price_snapshot' => ['required', 'numeric', 'gt:0'],
            'items.*.selected_stock_batch_ids' => ['nullable', 'array'],
            'items.*.selected_stock_batch_ids.*' => ['integer', 'distinct', 'exists:stock_batches,id'],
            'items.*.note' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['work_location_id' => 'lokasi kerja', 'items' => 'produk koreksi', 'items.*.stock_id' => 'stok produk', 'items.*.physical_qty' => 'stok fisik final', 'items.*.unit_cost_snapshot' => 'HPP koreksi positif', 'items.*.sale_price_snapshot' => 'harga jual lokasi', 'items.*.note' => 'catatan koreksi'];
    }
}
