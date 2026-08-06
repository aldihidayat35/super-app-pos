<?php

namespace App\Http\Requests\Warehouse;

use App\Enums\StockTransferDiscrepancyResolutionType;
use App\Models\StockTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveStockTransferDiscrepancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transfer = $this->route('stockTransfer');

        return $transfer instanceof StockTransfer && ($this->user()?->can('resolveDiscrepancy', $transfer) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stock_transfer_item_id' => ['required', 'integer', 'exists:stock_transfer_items,id'],
            'resolution_type' => ['required', Rule::enum(StockTransferDiscrepancyResolutionType::class)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['required', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'stock_transfer_item_id' => 'item transfer',
            'resolution_type' => 'jenis penyelesaian',
            'quantity' => 'jumlah penyelesaian',
            'notes' => 'alasan dan catatan',
            'proof' => 'bukti penyelesaian',
        ];
    }
}
