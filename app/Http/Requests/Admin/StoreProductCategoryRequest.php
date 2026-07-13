<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'exists:product_categories,id'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:product_categories,code'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'icon' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['parent_id' => 'parent kategori', 'code' => 'kode', 'name' => 'nama', 'sort_order' => 'urutan', 'is_active' => 'status'];
    }
}
