<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Http\FormRequest;

class SetPrimaryProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');
        $image = $this->route('productImage');

        return $product instanceof Product
            && $image instanceof ProductImage
            && $image->product_id === $product->id
            && ($this->user()?->can('update', $product) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
