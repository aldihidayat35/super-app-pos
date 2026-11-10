<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BatchProductQuickSetupRequest extends FormRequest
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
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'rows.*.barcode' => ['nullable', 'string', 'max:120'],
            'rows.*.hpp_unit_id' => ['required', 'integer', 'exists:units,id'],
            'rows.*.hpp_amount' => ['required', 'numeric', 'min:0'],
            'rows.*.stock_unit_id' => ['required', 'integer', 'exists:units,id'],
            'rows.*.stock_qty' => ['required', 'numeric', 'min:0'],
            'rows.*.sell_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'rows.*.min_price_amount' => ['nullable', 'numeric', 'min:0'],
            'rows.*.prices' => ['nullable', 'array'],
            'rows.*.unlimited' => ['nullable'],
            'rows.*.starts_at' => ['nullable', 'date'],
            'rows.*.ends_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'work_location_id' => 'Lokasi Kerja',
            'rows' => 'Daftar Baris Produk',
        ];
    }
}
