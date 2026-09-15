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

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'received_at' => ['required', 'date'],
            'delivery_note_number' => ['nullable', 'string', 'max:120'],
            'actual_freight_cost' => ['nullable', 'numeric', 'min:0'],
            'actual_additional_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'proof_path' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0'],
            'items.*.quantity_accepted' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_rejected' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_damaged' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity_returned_to_supplier' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_no' => ['nullable', 'string', 'max:120'],
            'items.*.qc_notes' => ['nullable', 'string', 'max:500'],
            'action' => ['nullable', 'in:draft,post'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $purchaseOrder = PurchaseOrder::query()->find($this->integer('purchase_order_id'));
            if (! $purchaseOrder) {
                return;
            }

            foreach ((array) $this->input('items', []) as $index => $item) {
                $locationId = (int) ($item['warehouse_location_id'] ?? 0);
                if ($locationId === 0) {
                    continue;
                }
                if (! $purchaseOrder->warehouse_id || ! WarehouseLocation::query()->whereKey($locationId)->where('warehouse_id', $purchaseOrder->warehouse_id)->exists()) {
                    $validator->errors()->add("items.{$index}.warehouse_location_id", 'Lokasi rak hanya boleh dipilih dari gudang tujuan PO. Penerimaan toko tidak memakai rak gudang utama.');
                }
            }
        });
    }
}
