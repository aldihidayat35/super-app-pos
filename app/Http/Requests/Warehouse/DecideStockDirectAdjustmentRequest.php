<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class DecideStockDirectAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock_direct_adjustments.approve') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['notes' => ['required', 'string', 'min:5', 'max:2000']];
    }

    public function attributes(): array
    {
        return ['notes' => 'alasan keputusan'];
    }
}
