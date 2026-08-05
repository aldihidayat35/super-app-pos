<?php

namespace App\Http\Requests\Warehouse;

use App\Models\Stock;
use App\Models\WarehouseLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_adjustments.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'work_location_id' => ['required', Rule::exists('work_locations', 'id')->where('is_active', true)],
            'warehouse_location_id' => ['nullable', Rule::exists('warehouse_locations', 'id')->where('is_active', true)],
            'category_id' => ['nullable', Rule::exists('product_categories', 'id')->where('is_active', true)],
            'pic_user_id' => ['nullable', 'exists:users,id'],
            'method' => ['required', Rule::in(['manual', 'scan', 'import'])],
            'freeze_stock' => ['nullable', 'boolean'],
            'blind_count' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date'],
            'threshold_qty' => ['nullable', 'numeric', 'min:0'],
            'threshold_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'action' => ['nullable', 'in:draft,start'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['work_location_id', 'warehouse_location_id', 'category_id'])) {
                return;
            }

            $workLocationId = $this->integer('work_location_id');
            $warehouseLocationId = $this->integer('warehouse_location_id');

            if ($warehouseLocationId > 0) {
                $belongsToWorkLocation = WarehouseLocation::query()
                    ->whereKey($warehouseLocationId)
                    ->whereHas('warehouse', fn ($query) => $query->where('work_location_id', $workLocationId))
                    ->exists();

                if (! $belongsToWorkLocation) {
                    $validator->errors()->add('warehouse_location_id', 'Zona/Rak/Bin tidak sesuai dengan gudang/cabang yang dipilih.');

                    return;
                }
            }

            if ($this->input('action') !== 'start') {
                return;
            }

            $hasStock = Stock::query()
                ->where('work_location_id', $workLocationId)
                ->when($warehouseLocationId > 0, fn ($query) => $query->where('warehouse_location_id', $warehouseLocationId))
                ->when($this->integer('category_id') > 0, fn ($query) => $query->whereHas(
                    'product',
                    fn ($product) => $product->where('category_id', $this->integer('category_id')),
                ))
                ->exists();

            if (! $hasStock) {
                $validator->errors()->add(
                    'work_location_id',
                    'Cakupan yang dipilih belum memiliki saldo stok. Simpan sebagai rancangan, pilih cakupan lain, atau masukkan stok melalui penerimaan barang/transfer stok sebelum menyimpan acuan stok.',
                );
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'work_location_id' => 'gudang/cabang',
            'warehouse_location_id' => 'zona/rak/bin',
            'category_id' => 'kategori produk',
            'pic_user_id' => 'PIC (Penanggung Jawab Opname)',
            'method' => 'metode',
            'scheduled_at' => 'tanggal opname',
            'threshold_qty' => 'batas toleransi jumlah',
            'threshold_value' => 'batas toleransi nilai kerugian',
            'notes' => 'catatan',
        ];
    }
}
