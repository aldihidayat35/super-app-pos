<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockTransferRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = [];

        foreach ((array) $this->input('items', []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (filled($item['product_id'] ?? null) || filled($item['quantity_requested'] ?? null) || filled($item['quantity_approved'] ?? null)) {
                $items[] = $item;
            }
        }

        $this->merge(['items' => $items]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('stock_transfers.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'restock_request_id' => ['nullable', 'exists:restock_requests,id'],
            'source_work_location_id' => ['required', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'source_warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'destination_work_location_id' => ['required', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'destination_warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'transfer_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'action' => ['nullable', 'in:draft,submit'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.unit_id' => ['nullable', Rule::exists('units', 'id')->where('is_active', true)],
            'items.*.source_warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'items.*.destination_warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'items.*.quantity_requested' => ['required', 'numeric', 'gt:0'],
            'items.*.quantity_approved' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                if (! is_array($item) || ! filled($item['quantity_approved'] ?? null) || ! filled($item['quantity_requested'] ?? null)) {
                    continue;
                }

                if (bccomp((string) $item['quantity_approved'], (string) $item['quantity_requested'], 4) === 1) {
                    $validator->errors()->add("items.{$index}.quantity_approved", 'Qty disetujui tidak boleh melebihi qty yang diminta.');
                }
            }
        }];
    }
}
