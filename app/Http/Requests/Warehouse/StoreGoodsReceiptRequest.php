<?php

namespace App\Http\Requests\Warehouse;

use App\Models\PurchaseOrder;
use App\Models\WarehouseLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('goods_receipts.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $clean = [];
        if ($this->has('actual_freight_cost') && is_string($this->actual_freight_cost)) {
            $clean['actual_freight_cost'] = str_replace(['.', ','], ['', '.'], $this->actual_freight_cost);
        }
        if ($this->has('actual_additional_cost') && is_string($this->actual_additional_cost)) {
            $clean['actual_additional_cost'] = str_replace(['.', ','], ['', '.'], $this->actual_additional_cost);
        }
        if ($this->has('tax_amount') && is_string($this->tax_amount)) {
            $clean['tax_amount'] = str_replace(['.', ','], ['', '.'], $this->tax_amount);
        }
        if ($this->has('items') && is_array($this->items)) {
            $items = $this->items;
            foreach ($items as $k => $item) {
                if (isset($item['unit_price']) && is_string($item['unit_price'])) {
                    $items[$k]['unit_price'] = str_replace(['.', ','], ['', '.'], $item['unit_price']);
                }
                if (isset($item['sale_price']) && is_string($item['sale_price'])) {
                    $items[$k]['sale_price'] = str_replace(['.', ','], ['', '.'], $item['sale_price']);
                }
                if (isset($item['actual_unit_cost']) && is_string($item['actual_unit_cost'])) {
                    $items[$k]['actual_unit_cost'] = str_replace(['.', ','], ['', '.'], $item['actual_unit_cost']);
                }
            }
            $clean['items'] = $items;
        }
        if ($clean !== []) {
            $this->merge($clean);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'supplier_id' => ['required_without:purchase_order_id', 'nullable', 'exists:suppliers,id'],
            'destination_work_location_id' => ['required_without:purchase_order_id', 'nullable', 'exists:work_locations,id'],
            'received_at' => ['required', 'date'],
            'delivery_note_number' => ['nullable', 'string', 'max:120'],
            'tax_invoice_number' => ['nullable', 'string', 'max:120'],
            'actual_freight_cost' => ['nullable', 'numeric', 'min:0'],
            'actual_additional_cost' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'is_tax_included' => ['nullable', 'boolean'],
            'is_tax_creditable' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'proof_path' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'exists:purchase_order_items,id'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0'],
            'items.*.quantity_accepted' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_rejected' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_damaged' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_returned_to_supplier' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.actual_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.sale_price' => ['required', 'numeric', 'gt:0'],
            'items.*.batch_no' => ['nullable', 'string', 'max:120'],
            'items.*.qc_notes' => ['nullable', 'string', 'max:500'],
            'action' => ['nullable', 'in:draft,post'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'Supplier',
            'destination_work_location_id' => 'Lokasi Penerima',
            'received_at' => 'Tanggal Penerimaan',
            'actual_freight_cost' => 'Biaya Ongkir',
            'items.*.product_id' => 'Produk',
            'items.*.unit_id' => 'Satuan',
            'items.*.quantity_received' => 'Jumlah Datang',
            'items.*.unit_price' => 'Harga Beli Satuan',
            'items.*.actual_unit_cost' => 'HPP aktual',
            'items.*.sale_price' => 'Harga jual lokasi',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $permittedLocations = $user?->permittedWorkLocationIds() ?? [];

            $locationId = (int) $this->input('destination_work_location_id');
            if ($locationId > 0 && ! in_array($locationId, $permittedLocations, true) && ! $user?->hasRole('super_admin')) {
                $validator->errors()->add('destination_work_location_id', 'Anda tidak memiliki hak akses pada lokasi penerima ini.');
            }

            $purchaseOrder = $this->filled('purchase_order_id')
                ? PurchaseOrder::query()->find($this->integer('purchase_order_id'))
                : null;

            foreach ((array) $this->input('items', []) as $index => $item) {
                $binId = (int) ($item['warehouse_location_id'] ?? 0);
                if ($binId > 0 && $purchaseOrder) {
                    if (! $purchaseOrder->warehouse_id || ! WarehouseLocation::query()->whereKey($binId)->where('warehouse_id', $purchaseOrder->warehouse_id)->exists()) {
                        $validator->errors()->add("items.{$index}.warehouse_location_id", 'Lokasi rak hanya boleh dipilih dari gudang tujuan PO.');
                    }
                }
            }
        });
    }
}
