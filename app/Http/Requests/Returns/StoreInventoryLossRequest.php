<?php

namespace App\Http\Requests\Returns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryLossRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('losses.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'work_location_id' => ['required', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'loss_type' => ['required', 'in:broken,lost,expired,opname_variance,damage,other'],
            'disposition' => ['required', 'in:damage,issue'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost_snapshot' => ['nullable', 'numeric', 'min:0'],
            'reference_type' => ['nullable', 'string', 'max:80'],
            'reference_id' => ['nullable', 'integer'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'evidence_path' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'work_location_id' => 'lokasi kerja',
            'warehouse_location_id' => 'zona/rak/bin',
            'product_id' => 'produk',
            'loss_type' => 'jenis kerugian',
            'disposition' => 'dampak ke stok',
            'quantity' => 'jumlah barang terdampak',
            'reason' => 'penyebab atau catatan',
        ];
    }
}
