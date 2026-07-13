<?php

namespace App\Http\Requests\Retail;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestockRequestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = [];

        foreach ((array) $this->input('items', []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (filled($item['product_id'] ?? null) || filled($item['quantity_requested'] ?? null)) {
                $items[] = $item;
            }
        }

        $this->merge(['items' => $items]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('stock_transfers.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true)],
            'source_warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'needed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'action' => ['nullable', 'in:draft,submit'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('status', 'active')],
            'items.*.quantity_requested' => ['required', 'numeric', 'gt:0'],
            'items.*.priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
