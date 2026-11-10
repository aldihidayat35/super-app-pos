<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveProductQuickSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $canManage = $user->can('products.quick_setup')
            || $user->can('products.update')
            || $user->can('products.create')
            || $user->can('retail.catalog.manage')
            || $user->hasRole(['kepala_toko', 'kepala_gudang', 'super_admin']);

        if (! $canManage) {
            return false;
        }

        $locationId = (int) $this->input('work_location_id');
        if (! $locationId) {
            return false;
        }

        return $user->hasUnrestrictedLocationScope() || $user->canAccessWorkLocation($locationId);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'work_location_id' => ['required', 'integer', 'exists:work_locations,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'hpp_unit_id' => ['required', 'integer', 'exists:units,id'],
            'hpp_amount' => ['required', 'numeric', 'min:0'],
            'stock_unit_id' => ['required', 'integer', 'exists:units,id'],
            'stock_qty' => ['required', 'numeric', 'min:0'],
            'sell_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'min_price_amount' => ['nullable', 'numeric', 'min:0'],
            'prices' => ['nullable', 'array'],
            'unlimited' => ['nullable'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'work_location_id' => 'Lokasi Kerja',
            'product_id' => 'Produk',
            'barcode' => 'Barcode',
            'hpp_unit_id' => 'Satuan HPP',
            'hpp_amount' => 'Nominal HPP',
            'stock_unit_id' => 'Satuan Stok',
            'stock_qty' => 'Jumlah Stok',
            'sell_unit_id' => 'Satuan Jual',
            'min_price_amount' => 'Harga Jual Minimum',
            'prices' => 'Ring Harga',
        ];
    }
}
