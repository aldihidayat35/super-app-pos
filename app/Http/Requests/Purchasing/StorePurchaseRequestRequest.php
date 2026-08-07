<?php

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('purchase_orders.create') ?? false) || ($this->user()?->can('stock.create') ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $workLocationIds = $this->user()?->permittedWorkLocationIds() ?? [];

        return [
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereIn('work_location_id', $workLocationIds)),
            ],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'reason' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.unit_id' => ['nullable', Rule::exists('units', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'warehouse_id' => 'gudang',
            'priority' => 'prioritas',
            'reason' => 'alasan permintaan',
            'items.*.product_id' => 'produk',
            'items.*.unit_id' => 'satuan',
            'items.*.quantity' => 'jumlah dibutuhkan',
            'items.*.reason' => 'catatan item',
        ];
    }
}
