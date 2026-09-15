<?php

namespace App\Http\Requests\Retail;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequestProposal extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product_requests.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $locationIds = $this->user()?->permittedWorkLocationIds() ?? [];

        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_active', true)->whereIn('work_location_id', $locationIds))],
            'name' => ['required', 'string', 'max:255'],
            'proposed_sku' => ['nullable', 'string', 'max:80', 'alpha_dash'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'category_id' => ['required', 'exists:product_categories,id'],
            'brand_id' => ['nullable', 'exists:product_brands,id'],
            'base_unit_id' => ['required', 'exists:units,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'proposed_selling_price' => ['required', 'numeric', 'gte:purchase_cost'],
            'description' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function attributes(): array
    {
        return [
            'branch_id' => 'toko pengaju', 'name' => 'nama produk', 'proposed_sku' => 'usulan SKU',
            'barcode' => 'barcode', 'category_id' => 'kategori', 'base_unit_id' => 'satuan dasar',
            'purchase_cost' => 'harga beli', 'proposed_selling_price' => 'usulan harga jual',
        ];
    }
}
