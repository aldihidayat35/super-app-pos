<?php

namespace App\Http\Requests\Retail;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('returns.create') ?? false) || ($this->user()?->can('pos.void') ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'in:refund,exchange,credit'],
            'refund_method' => ['nullable', 'in:cash,bank_transfer,qris,manual'],
            'reason' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.pos_sale_item_id' => ['required', 'integer', 'exists:pos_sale_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.condition' => ['required', Rule::in(['good', 'damaged'])],
            'items.*.reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
