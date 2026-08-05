<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['nullable', 'image', 'max:4096'],
            'remove_photos' => ['nullable', 'string'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $product = $this->route('product');
            if (! $product instanceof Product) {
                return;
            }

            $removed = array_filter(array_map('trim', explode(',', (string) $this->input('remove_photos', ''))));
            $removedCount = $product->images()->whereIn('path', $removed)->count();
            $existingCount = max(0, $product->images()->count() - $removedCount);
            $incomingCount = count((array) $this->file('photos', [])) + ($this->hasFile('main_image') ? 1 : 0);

            if ($existingCount + $incomingCount > 3) {
                $validator->errors()->add('photos', 'Jumlah foto produk maksimal 3 file. Hapus foto lama terlebih dahulu.');
            }
        });
    }
}
