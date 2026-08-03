<?php

namespace App\Http\Requests\Admin;

use App\Enums\BarcodePaperSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductBarcodePrintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.print_barcode') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('status', 'active')],
            'label_count' => ['required', 'integer', 'min:1', 'max:100'],
            'paper_size' => ['required', Rule::enum(BarcodePaperSize::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'product_id' => filled($this->query('product_id')) ? $this->query('product_id') : null,
            'label_count' => $this->query('label_count', 1),
            'paper_size' => $this->query('paper_size', BarcodePaperSize::A4->value),
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'product_id' => 'produk',
            'label_count' => 'jumlah label',
            'paper_size' => 'ukuran kertas',
        ];
    }
}
