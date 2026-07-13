<?php

namespace App\Http\Requests\Warehouse;

use App\Enums\StockOpnameReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CountStockOpnameItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_adjustments.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'counted_qty' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', Rule::in(array_keys(StockOpnameReason::options()))],
            'note' => ['nullable', 'string', 'max:500'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'evidence_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
