<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.update') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $category = $this->route('product_category');

        return [
            'parent_id' => ['nullable', 'exists:product_categories,id', Rule::notIn([$category?->id])],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('product_categories', 'code')->ignore($category)],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'icon' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}
