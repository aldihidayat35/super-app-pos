<?php

namespace App\Http\Requests\Warehouse;

use App\Enums\StockOpnameReason;
use App\Models\StockOpnameItem;
use App\Support\Decimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CountStockOpnameItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_adjustments.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'counted_qty' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', Rule::in(array_keys(StockOpnameReason::options()))],
            'note' => ['nullable', 'string', 'max:500'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'evidence_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('counted_qty')) {
                return;
            }

            $item = $this->route('item');
            if ($item instanceof StockOpnameItem
                && Decimal::compare(Decimal::normalize($this->input('counted_qty')), (string) $item->system_qty_snapshot) !== 0
                && blank($this->input('reason'))) {
                $validator->errors()->add('reason', 'Alasan wajib dipilih ketika jumlah fisik berbeda dari stok sistem.');
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['counted_qty' => 'jumlah fisik', 'reason' => 'alasan selisih', 'evidence' => 'bukti pendukung'];
    }
}
