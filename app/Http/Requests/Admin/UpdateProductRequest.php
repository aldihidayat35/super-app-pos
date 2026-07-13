<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'sku' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('products', 'sku')->ignore($product)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:product_categories,id'],
            'subcategory_id' => ['nullable', 'exists:product_categories,id'],
            'brand_id' => ['nullable', 'exists:product_brands,id'],
            'model' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'material' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'base_unit_id' => ['required', 'exists:units,id'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'minimum_order' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'safety_stock' => ['required', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'volume' => ['nullable', 'numeric', 'min:0'],
            'default_warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_price' => ['nullable', 'numeric', 'min:0'],
            'main_image' => ['nullable', 'image', 'max:4096'],
            'barcodes' => ['nullable', 'array'],
            'barcodes.*.id' => ['nullable', 'exists:product_barcodes,id'],
            'barcodes.*.code' => ['nullable', 'string', 'max:120', 'distinct'],
            'barcodes.*.type' => ['required_with:barcodes.*.code', 'in:barcode,qr'],
            'units' => ['required', 'array', 'min:1'],
            'units.*.unit_id' => ['required', 'exists:units,id', 'distinct'],
            'units.*.name' => ['nullable', 'string', 'max:100'],
            'units.*.conversion_factor' => ['required', 'numeric', 'gt:0'],
            'units.*.is_sellable' => ['boolean'],
            'units.*.is_active' => ['boolean'],
        ];
    }
}
